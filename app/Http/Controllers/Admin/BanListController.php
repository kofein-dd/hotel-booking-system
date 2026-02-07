<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BanList;
use App\Models\User;
use App\Http\Requests\BanList\StoreBanListRequest;
use App\Http\Requests\BanList\UpdateBanListRequest;
use App\Http\Resources\BanListResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BanListController extends Controller
{
    /**
     * Display a listing of the ban list entries.
     */
    public function index(Request $request)
    {
        try {
            $query = BanList::with(['user', 'bannedBy'])
                ->orderBy('banned_until', 'desc');

            // Фильтр по статусу
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'active':
                        $query->where(function ($q) {
                            $q->where('banned_until', '>', now())
                                ->orWhere('banned_until', null);
                        });
                        break;
                    case 'expired':
                        $query->where('banned_until', '<', now())
                            ->where('banned_until', '!=', null);
                        break;
                    case 'permanent':
                        $query->where('banned_until', null);
                        break;
                }
            }

            // Поиск по пользователю
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('user_email') && $request->user_email) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('email', 'LIKE', '%' . $request->user_email . '%');
                });
            }

            // Поиск по администратору
            if ($request->has('admin_id') && $request->admin_id) {
                $query->where('banned_by', $request->admin_id);
            }

            // Поиск по причине
            if ($request->has('reason') && $request->reason) {
                $query->where('reason', 'LIKE', '%' . $request->reason . '%');
            }

            // Поиск по IP
            if ($request->has('ip_address') && $request->ip_address) {
                $query->where('ip_address', 'LIKE', '%' . $request->ip_address . '%');
            }

            // Статистика
            $stats = [
                'total_bans' => BanList::count(),
                'active_bans' => BanList::where(function ($q) {
                    $q->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                })->count(),
                'expired_bans' => BanList::where('banned_until', '<', now())
                    ->where('banned_until', '!=', null)
                    ->count(),
                'permanent_bans' => BanList::where('banned_until', null)->count(),
                'today_bans' => BanList::whereDate('created_at', today())->count(),
            ];

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $bans = $query->paginate($perPage);

            // Для API
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => BanListResource::collection($bans),
                    'stats' => $stats,
                    'meta' => [
                        'total' => $bans->total(),
                        'per_page' => $bans->perPage(),
                        'current_page' => $bans->currentPage(),
                        'last_page' => $bans->lastPage(),
                    ]
                ]);
            }

            // Для веб-интерфейса
            return view('admin.ban-list.index', compact('bans', 'stats'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении списка банов',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()->with('error', 'Ошибка при получении списка банов: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new ban entry.
     */
    public function create(Request $request)
    {
        try {
            $users = User::whereDoesntHave('bans', function ($query) {
                $query->where(function ($q) {
                    $q->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                });
            })->get(['id', 'name', 'email']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'users' => $users
                ]);
            }

            return view('admin.ban-list.create', compact('users'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при подготовке формы'
                ], 500);
            }

            return redirect()->back()->with('error', 'Ошибка при подготовке формы: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created ban entry.
     */
    public function store(StoreBanListRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($request->user_id);

            // Проверяем, не забанен ли уже пользователь
            $existingBan = BanList::where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                })->first();

            if ($existingBan) {
                throw new \Exception('Пользователь уже заблокирован');
            }

            // Создаем запись в бан-листе
            $banData = [
                'user_id' => $user->id,
                'banned_by' => auth()->id(),
                'reason' => $request->reason,
                'ip_address' => $request->ip_address ?: request()->ip(),
                'banned_until' => $request->banned_until,
                'additional_info' => $request->additional_info,
            ];

            $ban = BanList::create($banData);

            // Обновляем статус пользователя
            $user->update([
                'status' => 'banned',
                'banned_until' => $request->banned_until
            ]);

            // Логируем действие
            $this->logBanAction($user, 'ban', $ban->reason);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Пользователь успешно заблокирован',
                    'data' => new BanListResource($ban)
                ], 201);
            }

            return redirect()->route('admin.ban-list.index')
                ->with('success', 'Пользователь успешно заблокирован');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при блокировке пользователя',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ошибка при блокировке пользователя: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified ban entry.
     */
    public function show(Request $request, $id)
    {
        try {
            $ban = BanList::with(['user', 'bannedBy'])->findOrFail($id);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => new BanListResource($ban)
                ]);
            }

            return view('admin.ban-list.show', compact('ban'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Запись в бан-листе не найдена'
                ], 404);
            }

            return redirect()->route('admin.ban-list.index')
                ->with('error', 'Запись в бан-листе не найдена');
        }
    }

    /**
     * Show the form for editing the specified ban entry.
     */
    public function edit(Request $request, $id)
    {
        try {
            $ban = BanList::with('user')->findOrFail($id);
            $users = User::get(['id', 'name', 'email']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'ban' => new BanListResource($ban),
                    'users' => $users
                ]);
            }

            return view('admin.ban-list.edit', compact('ban', 'users'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Запись в бан-листе не найдена'
                ], 404);
            }

            return redirect()->route('admin.ban-list.index')
                ->with('error', 'Запись в бан-листе не найдена');
        }
    }

    /**
     * Update the specified ban entry.
     */
    public function update(UpdateBanListRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $ban = BanList::with('user')->findOrFail($id);
            $user = $ban->user;

            $banData = [
                'reason' => $request->reason,
                'banned_until' => $request->banned_until,
                'additional_info' => $request->additional_info,
            ];

            // Если обновляем дату окончания бана
            if ($ban->banned_until != $request->banned_until) {
                $user->update([
                    'banned_until' => $request->banned_until
                ]);
            }

            $ban->update($banData);

            // Логируем действие
            $this->logBanAction($user, 'update_ban', 'Обновлена информация о блокировке');

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Информация о блокировке обновлена',
                    'data' => new BanListResource($ban)
                ]);
            }

            return redirect()->route('admin.ban-list.index')
                ->with('success', 'Информация о блокировке обновлена');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при обновлении информации о блокировке',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ошибка при обновлении информации о блокировке: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified ban entry (unban user).
     */
    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ban = BanList::with('user')->findOrFail($id);
            $user = $ban->user;

            // Восстанавливаем пользователя
            $user->update([
                'status' => 'active',
                'banned_until' => null
            ]);

            // Помечаем бан как снятый
            $ban->update([
                'unbanned_at' => now(),
                'unbanned_by' => auth()->id(),
                'unban_reason' => $request->get('reason', 'Разблокирован администратором')
            ]);

            // Логируем действие
            $this->logBanAction($user, 'unban', $request->get('reason', 'Разблокирован администратором'));

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Пользователь успешно разблокирован'
                ]);
            }

            return redirect()->route('admin.ban-list.index')
                ->with('success', 'Пользователь успешно разблокирован');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при разблокировке пользователя',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при разблокировке пользователя: ' . $e->getMessage());
        }
    }

    /**
     * Quick ban user.
     */
    public function quickBan(Request $request, $userId)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);

            // Проверяем, не забанен ли уже
            $existingBan = BanList::where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                })->first();

            if ($existingBan) {
                throw new \Exception('Пользователь уже заблокирован');
            }

            // Создаем бан
            $ban = BanList::create([
                'user_id' => $user->id,
                'banned_by' => auth()->id(),
                'reason' => $request->get('reason', 'Нарушение правил'),
                'ip_address' => request()->ip(),
                'banned_until' => $request->get('banned_until'),
                'additional_info' => $request->get('additional_info'),
            ]);

            // Обновляем пользователя
            $user->update([
                'status' => 'banned',
                'banned_until' => $ban->banned_until
            ]);

            // Логируем
            $this->logBanAction($user, 'quick_ban', $ban->reason);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Пользователь успешно заблокирован',
                    'data' => new BanListResource($ban)
                ]);
            }

            return redirect()->back()
                ->with('success', 'Пользователь успешно заблокирован');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при блокировке',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при блокировке: ' . $e->getMessage());
        }
    }

    /**
     * Check if user is banned.
     */
    public function checkBan(Request $request, $userId)
    {
        try {
            $ban = BanList::where('user_id', $userId)
                ->where(function ($query) {
                    $query->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                })
                ->whereNull('unbanned_at')
                ->first();

            $isBanned = (bool) $ban;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'is_banned' => $isBanned,
                    'ban_info' => $ban ? new BanListResource($ban) : null
                ]);
            }

            return response()->json([
                'is_banned' => $isBanned,
                'ban' => $ban
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при проверке блокировки'
                ], 500);
            }

            return response()->json([
                'error' => 'Ошибка при проверке блокировки'
            ], 500);
        }
    }

    /**
     * Get ban statistics.
     */
    public function statistics(Request $request)
    {
        try {
            $period = $request->get('period', '30days');

            switch ($period) {
                case '7days':
                    $startDate = Carbon::now()->subDays(7);
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(30);
                    break;
                case '90days':
                    $startDate = Carbon::now()->subDays(90);
                    break;
                default:
                    $startDate = Carbon::now()->subDays(30);
            }

            // Баны по дням
            $bansByDay = BanList::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->date => $item->count];
                });

            // Причины банов (топ 10)
            $banReasons = BanList::select(
                DB::raw('SUBSTRING(reason, 1, 50) as reason'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('reason')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Администраторы, которые больше всего банили
            $activeAdmins = BanList::select(
                'banned_by',
                DB::raw('COUNT(*) as count')
            )
                ->with('bannedBy')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('banned_by')
                ->groupBy('banned_by')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Длительность банов
            $banDurations = BanList::select(
                DB::raw('CASE
                    WHEN banned_until IS NULL THEN "permanent"
                    WHEN DATEDIFF(banned_until, created_at) <= 7 THEN "1-7 days"
                    WHEN DATEDIFF(banned_until, created_at) <= 30 THEN "1-30 days"
                    WHEN DATEDIFF(banned_until, created_at) <= 90 THEN "1-90 days"
                    ELSE "90+ days"
                END as duration'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('banned_until')
                ->groupBy('duration')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'bans_by_day' => $bansByDay,
                    'ban_reasons' => $banReasons,
                    'active_admins' => $activeAdmins,
                    'ban_durations' => $banDurations,
                    'period' => $period,
                    'total_bans_period' => BanList::where('created_at', '>=', $startDate)->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики'
            ], 500);
        }
    }

    /**
     * Export ban list.
     */
    public function export(Request $request)
    {
        try {
            $query = BanList::with(['user', 'bannedBy'])
                ->orderBy('created_at', 'desc');

            // Применение фильтров
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            $bans = $query->get();

            $format = $request->get('format', 'csv');

            if ($format === 'excel') {
                return $this->exportToExcel($bans);
            }

            return $this->exportToCsv($bans);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка при экспорте: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to export to CSV.
     */
    private function exportToCsv($bans)
    {
        $fileName = 'ban-list-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function() use ($bans) {
            $file = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($file, [
                'ID',
                'ID пользователя',
                'Email пользователя',
                'Имя пользователя',
                'Заблокировал',
                'Причина',
                'Дата блокировки',
                'Заблокирован до',
                'IP адрес',
                'Дополнительная информация',
                'Дата разблокировки',
                'Причина разблокировки'
            ]);

            // Данные
            foreach ($bans as $ban) {
                fputcsv($file, [
                    $ban->id,
                    $ban->user_id,
                    $ban->user ? $ban->user->email : '-',
                    $ban->user ? $ban->user->name : '-',
                    $ban->bannedBy ? $ban->bannedBy->email : '-',
                    $ban->reason,
                    $ban->created_at->format('Y-m-d H:i:s'),
                    $ban->banned_until ? $ban->banned_until->format('Y-m-d H:i:s') : 'Навсегда',
                    $ban->ip_address,
                    $ban->additional_info,
                    $ban->unbanned_at ? $ban->unbanned_at->format('Y-m-d H:i:s') : '-',
                    $ban->unban_reason ?: '-'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Log ban action.
     */
    private function logBanAction($user, $action, $reason)
    {
        // Используем существующую систему аудита
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'admin_id' => auth()->id(),
            'action_type' => $action,
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => "Пользователь {$user->email} - $reason",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }
}
