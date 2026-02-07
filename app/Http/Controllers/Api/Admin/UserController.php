<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BanList;
use App\Models\Booking;
use App\Models\AuditLog;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Поиск по имени, email или телефону
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%")
                        ->orWhere('email', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            }

            // Фильтр по роли
            if ($request->has('role') && $request->role) {
                $query->where('role', $request->role);
            }

            // Фильтр по статусу
            if ($request->has('status') && $request->status) {
                if ($request->status === 'banned') {
                    $query->where('status', 'banned');
                } elseif ($request->status === 'active') {
                    $query->where('status', 'active');
                } elseif ($request->status === 'inactive') {
                    $query->where('status', 'inactive');
                }
            }

            // Фильтр по дате регистрации
            if ($request->has('registered_from') && $request->registered_from) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->registered_from));
            }

            if ($request->has('registered_to') && $request->registered_to) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->registered_to));
            }

            // Фильтр по активности (последний вход)
            if ($request->has('last_login_from') && $request->last_login_from) {
                $query->whereDate('last_login_at', '>=', Carbon::parse($request->last_login_from));
            }

            if ($request->has('last_login_to') && $request->last_login_to) {
                $query->whereDate('last_login_at', '<=', Carbon::parse($request->last_login_to));
            }

            // Статистика
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'banned_users' => User::where('status', 'banned')->count(),
                'inactive_users' => User::where('status', 'inactive')->count(),
                'today_registrations' => User::whereDate('created_at', today())->count(),
                'admin_users' => User::where('role', 'admin')->count(),
                'user_users' => User::where('role', 'user')->count(),
            ];

            // Сортировка
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($users),
                'stats' => $stats,
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка пользователей',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Хешируем пароль, если он указан
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Если не указана роль, устанавливаем по умолчанию 'user'
            if (!isset($data['role'])) {
                $data['role'] = 'user';
            }

            // Если не указан статус, устанавливаем по умолчанию 'active'
            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }

            // Создаем пользователя
            $user = User::create($data);

            // Логируем действие
            $this->logAction($user, 'create', 'Создан новый пользователь администратором');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно создан',
                'data' => new UserResource($user)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании пользователя',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, $id)
    {
        try {
            $user = User::with([
                'bookings' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                },
                'reviews' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                },
                'payments' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                },
                'bans' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])->findOrFail($id);

            // Статистика пользователя
            $userStats = [
                'total_bookings' => $user->bookings()->count(),
                'active_bookings' => $user->bookings()->where('status', 'confirmed')->count(),
                'total_spent' => $user->payments()->where('status', 'completed')->sum('amount'),
                'total_reviews' => $user->reviews()->count(),
                'avg_rating' => $user->reviews()->avg('rating'),
                'last_activity' => $user->last_login_at,
                'days_since_registration' => $user->created_at->diffInDays(now()),
            ];

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'stats' => $userStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден'
            ], 404);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $data = $request->validated();

            // Сохраняем старые значения для логов
            $oldValues = $user->toArray();

            // Если обновляется пароль - хешируем его
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                // Убираем пароль из данных, чтобы не перезаписывать
                unset($data['password']);
            }

            // Обновляем пользователя
            $user->update($data);

            // Логируем изменения
            $changedFields = array_diff_assoc($user->toArray(), $oldValues);
            if (!empty($changedFields)) {
                $this->logAction($user, 'update', 'Обновлены данные пользователя', $oldValues, $changedFields);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Данные пользователя обновлены',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении пользователя',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $currentUser = $request->user();

            // Нельзя удалить самого себя
            if ($user->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалить собственный аккаунт'
                ], 400);
            }

            // Проверяем, есть ли активные бронирования
            $activeBookings = $user->bookings()
                ->whereIn('status', ['pending', 'confirmed', 'active'])
                ->exists();

            if ($activeBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалить пользователя с активными бронированиями'
                ], 400);
            }

            // Логируем перед удалением
            $this->logAction($user, 'delete', 'Пользователь удален администратором');

            // Вместо полного удаления помечаем как удаленного
            $user->update([
                'status' => 'deleted',
                'deleted_at' => now(),
                'deleted_by' => $currentUser->id,
                'email' => $user->email . '_deleted_' . time(),
                'phone' => $user->phone ? $user->phone . '_deleted_' . time() : null,
            ]);

            // Альтернативно: полное удаление
            // $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Пользователь удален'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении пользователя',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Ban user.
     */
    public function ban(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $currentUser = $request->user();

            // Проверяем, не забанен ли уже пользователь
            $activeBan = BanList::where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                })
                ->whereNull('unbanned_at')
                ->first();

            if ($activeBan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь уже заблокирован'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'banned_until' => 'nullable|date|after:now',
                'additional_info' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Создаем запись в бан-листе
            $ban = BanList::create([
                'user_id' => $user->id,
                'banned_by' => $currentUser->id,
                'reason' => $request->reason,
                'ip_address' => $request->ip(),
                'banned_until' => $request->banned_until,
                'additional_info' => $request->additional_info,
            ]);

            // Обновляем статус пользователя
            $user->update([
                'status' => 'banned',
                'banned_until' => $request->banned_until
            ]);

            // Отменяем активные бронирования пользователя
            $this->cancelUserBookings($user);

            // Логируем действие
            $this->logAction($user, 'ban', "Пользователь заблокирован. Причина: {$request->reason}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно заблокирован',
                'data' => [
                    'user' => new UserResource($user),
                    'ban' => $ban
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при блокировке пользователя',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Unban user.
     */
    public function unban(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $currentUser = $request->user();

            // Находим активный бан
            $activeBan = BanList::where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('banned_until', '>', now())
                        ->orWhere('banned_until', null);
                })
                ->whereNull('unbanned_at')
                ->first();

            if (!$activeBan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не заблокирован'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Обновляем запись в бан-листе
            $activeBan->update([
                'unbanned_at' => now(),
                'unbanned_by' => $currentUser->id,
                'unban_reason' => $request->reason
            ]);

            // Восстанавливаем пользователя
            $user->update([
                'status' => 'active',
                'banned_until' => null
            ]);

            // Логируем действие
            $this->logAction($user, 'unban', "Пользователь разблокирован. Причина: {$request->reason}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно разблокирован',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при разблокировке пользователя',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get user bookings.
     */
    public function bookings(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $query = $user->bookings()->with(['room', 'payments'])
                ->orderBy('created_at', 'desc');

            // Фильтрация по статусу
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Фильтрация по дате
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('check_in', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('check_out', '<=', Carbon::parse($request->date_to));
            }

            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $bookings = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении бронирований пользователя'
            ], 500);
        }
    }

    /**
     * Get user activity logs.
     */
    public function activityLogs(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $query = AuditLog::where('user_id', $user->id)
                ->orWhere('admin_id', $user->id)
                ->with(['user', 'admin'])
                ->orderBy('created_at', 'desc');

            // Фильтрация по типу действия
            if ($request->has('action_type') && $request->action_type) {
                $query->where('action_type', $request->action_type);
            }

            // Фильтрация по дате
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => AuditLogResource::collection($logs),
                'meta' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении логов активности'
            ], 500);
        }
    }

    /**
     * Get user statistics.
     */
    public function statistics(Request $request, $id = null)
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

            if ($id) {
                // Статистика конкретного пользователя
                $user = User::findOrFail($id);

                $userStats = [
                    'bookings' => [
                        'total' => $user->bookings()->where('created_at', '>=', $startDate)->count(),
                        'confirmed' => $user->bookings()->where('status', 'confirmed')->where('created_at', '>=', $startDate)->count(),
                        'cancelled' => $user->bookings()->where('status', 'cancelled')->where('created_at', '>=', $startDate)->count(),
                        'by_month' => $this->getUserBookingsByMonth($user, $startDate),
                    ],
                    'payments' => [
                        'total_amount' => $user->payments()->where('status', 'completed')->where('created_at', '>=', $startDate)->sum('amount'),
                        'count' => $user->payments()->where('created_at', '>=', $startDate)->count(),
                        'average' => $user->payments()->where('status', 'completed')->where('created_at', '>=', $startDate)->avg('amount'),
                    ],
                    'reviews' => [
                        'total' => $user->reviews()->where('created_at', '>=', $startDate)->count(),
                        'average_rating' => $user->reviews()->where('created_at', '>=', $startDate)->avg('rating'),
                    ],
                    'activity' => [
                        'logins_count' => AuditLog::where('user_id', $user->id)
                            ->where('action_type', 'login')
                            ->where('created_at', '>=', $startDate)
                            ->count(),
                        'last_login' => $user->last_login_at,
                        'days_active' => $this->getActiveDaysCount($user, $startDate),
                    ]
                ];

                return response()->json([
                    'success' => true,
                    'data' => $userStats,
                    'period' => $period
                ]);

            } else {
                // Общая статистика по всем пользователям
                $stats = [
                    'registrations' => [
                        'total' => User::where('created_at', '>=', $startDate)->count(),
                        'by_day' => $this->getRegistrationsByDay($startDate),
                        'by_source' => $this->getRegistrationsBySource($startDate),
                    ],
                    'activity' => [
                        'active_users' => User::where('last_login_at', '>=', Carbon::now()->subDays(30))->count(),
                        'new_users_today' => User::whereDate('created_at', today())->count(),
                        'user_growth' => $this->getUserGrowth($startDate),
                    ],
                    'segmentation' => [
                        'by_role' => User::select('role', DB::raw('COUNT(*) as count'))
                            ->where('created_at', '>=', $startDate)
                            ->groupBy('role')
                            ->get(),
                        'by_status' => User::select('status', DB::raw('COUNT(*) as count'))
                            ->where('created_at', '>=', $startDate)
                            ->groupBy('status')
                            ->get(),
                    ]
                ];

                return response()->json([
                    'success' => true,
                    'data' => $stats,
                    'period' => $period
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export users list.
     */
    public function export(Request $request)
    {
        try {
            $query = User::query();

            // Применяем фильтры
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            $format = $request->get('format', 'csv');

            if ($format === 'excel') {
                return $this->exportToExcel($users);
            }

            return $this->exportToCsv($users);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при экспорте пользователей',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk actions on users.
     */
    public function bulkActions(Request $request)
    {
        try {
            DB::beginTransaction();

            $action = $request->action;
            $userIds = $request->user_ids;
            $currentUser = $request->user();

            if (empty($userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не выбраны пользователи'
                ], 400);
            }

            $users = User::whereIn('id', $userIds)->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователи не найдены'
                ], 404);
            }

            $processed = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    switch ($action) {
                        case 'ban':
                            if ($user->status !== 'banned') {
                                BanList::create([
                                    'user_id' => $user->id,
                                    'banned_by' => $currentUser->id,
                                    'reason' => $request->reason ?? 'Массовая блокировка',
                                    'ip_address' => $request->ip(),
                                ]);
                                $user->update(['status' => 'banned']);
                                $this->cancelUserBookings($user);
                            }
                            break;

                        case 'unban':
                            if ($user->status === 'banned') {
                                BanList::where('user_id', $user->id)
                                    ->whereNull('unbanned_at')
                                    ->update([
                                        'unbanned_at' => now(),
                                        'unbanned_by' => $currentUser->id,
                                        'unban_reason' => $request->reason ?? 'Массовая разблокировка'
                                    ]);
                                $user->update(['status' => 'active', 'banned_until' => null]);
                            }
                            break;

                        case 'activate':
                            $user->update(['status' => 'active']);
                            break;

                        case 'deactivate':
                            $user->update(['status' => 'inactive']);
                            break;

                        case 'delete':
                            if ($user->id !== $currentUser->id) {
                                $user->update([
                                    'status' => 'deleted',
                                    'deleted_at' => now(),
                                    'deleted_by' => $currentUser->id,
                                ]);
                            }
                            break;

                        case 'change_role':
                            if ($request->has('role')) {
                                $user->update(['role' => $request->role]);
                            }
                            break;

                        default:
                            throw new \Exception('Неизвестное действие');
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Обработано $processed пользователей",
                'processed' => $processed,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при массовых действиях',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper method to export users to CSV.
     */
    private function exportToCsv($users)
    {
        $fileName = 'users-export-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($file, [
                'ID',
                'Имя',
                'Email',
                'Телефон',
                'Роль',
                'Статус',
                'Дата регистрации',
                'Последний вход',
                'Всего бронирований',
                'Всего потрачено',
                'Количество отзывов',
                'Средний рейтинг',
                'Заблокирован до'
            ]);

            // Данные
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone ?? '-',
                    $this->getRoleLabel($user->role),
                    $this->getStatusLabel($user->status),
                    $user->created_at->format('Y-m-d H:i:s'),
                    $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : '-',
                    $user->bookings()->count(),
                    $user->payments()->where('status', 'completed')->sum('amount'),
                    $user->reviews()->count(),
                    $user->reviews()->avg('rating') ?? '-',
                    $user->banned_until ? $user->banned_until->format('Y-m-d H:i:s') : '-'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper method to cancel user bookings.
     */
    private function cancelUserBookings($user)
    {
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '>', now())
            ->get();

        foreach ($activeBookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => 'Пользователь заблокирован',
                'cancelled_at' => now(),
                'cancelled_by' => 'system'
            ]);

            // Возвращаем оплату, если есть
            if ($booking->payment && $booking->payment->status === 'completed') {
                $booking->payment->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'refund_reason' => 'Пользователь заблокирован'
                ]);
            }
        }
    }

    /**
     * Helper method to log actions.
     */
    private function logAction($user, $actionType, $description, $oldValues = null, $newValues = null)
    {
        AuditLog::create([
            'user_id' => $user->id,
            'admin_id' => request()->user()->id,
            'action_type' => $actionType,
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }

    /**
     * Helper method to get role label.
     */
    private function getRoleLabel($role)
    {
        $labels = [
            'admin' => 'Администратор',
            'user' => 'Пользователь',
            'moderator' => 'Модератор',
        ];

        return $labels[$role] ?? $role;
    }

    /**
     * Helper method to get status label.
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'active' => 'Активен',
            'inactive' => 'Неактивен',
            'banned' => 'Заблокирован',
            'deleted' => 'Удален',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Get user bookings by month.
     */
    private function getUserBookingsByMonth($user, $startDate)
    {
        return $user->bookings()
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->month => $item->count];
            });
    }

    /**
     * Get active days count.
     */
    private function getActiveDaysCount($user, $startDate)
    {
        return AuditLog::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'))
            ->distinct()
            ->count();
    }

    /**
     * Get registrations by day.
     */
    private function getRegistrationsByDay($startDate)
    {
        return User::select(
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
    }

    /**
     * Get registrations by source.
     */
    private function getRegistrationsBySource($startDate)
    {
        return User::select(
            'registration_source',
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('registration_source')
            ->groupBy('registration_source')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Get user growth.
     */
    private function getUserGrowth($startDate)
    {
        $current = User::where('created_at', '>=', $startDate)->count();
        $previousStart = Carbon::parse($startDate)->subDays(30);
        $previousEnd = $startDate;
        $previous = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        if ($previous == 0) {
            return 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
