<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Http\Requests\AuditLog\SearchAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index(SearchAuditLogRequest $request)
    {
        try {
            $query = AuditLog::with(['user', 'admin'])
                ->orderBy('created_at', 'desc');

            // Поиск по пользователю
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Поиск по администратору
            if ($request->has('admin_id') && $request->admin_id) {
                $query->where('admin_id', $request->admin_id);
            }

            // Поиск по типу действия
            if ($request->has('action_type') && $request->action_type) {
                $query->where('action_type', $request->action_type);
            }

            // Поиск по модели
            if ($request->has('model_type') && $request->model_type) {
                $query->where('model_type', $request->model_type);
            }

            // Поиск по ID модели
            if ($request->has('model_id') && $request->model_id) {
                $query->where('model_id', $request->model_id);
            }

            // Поиск по IP адресу
            if ($request->has('ip_address') && $request->ip_address) {
                $query->where('ip_address', 'LIKE', '%' . $request->ip_address . '%');
            }

            // Поиск по диапазону дат
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            // Поиск по ключевому слову в описании
            if ($request->has('description') && $request->description) {
                $query->where('description', 'LIKE', '%' . $request->description . '%');
            }

            // Получение статистики
            $stats = [
                'total_logs' => AuditLog::count(),
                'today_logs' => AuditLog::whereDate('created_at', today())->count(),
                'unique_users' => AuditLog::distinct('user_id')->count('user_id'),
                'unique_admins' => AuditLog::distinct('admin_id')->whereNotNull('admin_id')->count('admin_id'),
            ];

            // Группировка по типам действий
            $actionTypes = AuditLog::select('action_type', DB::raw('COUNT(*) as count'))
                ->groupBy('action_type')
                ->orderBy('count', 'desc')
                ->get();

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $logs = $query->paginate($perPage);

            // Для веб-интерфейса
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => AuditLogResource::collection($logs),
                    'stats' => $stats,
                    'action_types' => $actionTypes,
                    'meta' => [
                        'total' => $logs->total(),
                        'per_page' => $logs->perPage(),
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                    ]
                ]);
            }

            // Для blade-шаблонов
            return view('admin.audit-logs.index', compact('logs', 'stats', 'actionTypes'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении логов аудита',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()->with('error', 'Ошибка при получении логов аудита: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified audit log.
     */
    public function show(Request $request, $id)
    {
        try {
            $log = AuditLog::with(['user', 'admin'])->findOrFail($id);

            // Форматирование измененных данных
            $changes = [];
            if ($log->old_values) {
                $oldValues = json_decode($log->old_values, true);
                $newValues = json_decode($log->new_values, true);

                foreach ($oldValues as $key => $oldValue) {
                    $changes[] = [
                        'field' => $this->getFieldLabel($key),
                        'old' => $oldValue,
                        'new' => $newValues[$key] ?? null,
                        'type' => $this->getChangeType($oldValue, $newValues[$key] ?? null)
                    ];
                }
            }

            // Для API
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => new AuditLogResource($log),
                    'changes' => $changes
                ]);
            }

            // Для blade-шаблонов
            return view('admin.audit-logs.show', compact('log', 'changes'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Лог аудита не найден'
                ], 404);
            }

            return redirect()->route('admin.audit-logs.index')
                ->with('error', 'Лог аудита не найден');
        }
    }

    /**
     * Export audit logs to CSV or Excel
     */
    public function export(Request $request)
    {
        try {
            $query = AuditLog::with(['user', 'admin'])
                ->orderBy('created_at', 'desc');

            // Применение фильтров
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            $logs = $query->get();

            $format = $request->get('format', 'csv');

            if ($format === 'excel') {
                return $this->exportToExcel($logs);
            }

            return $this->exportToCsv($logs);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка при экспорте: ' . $e->getMessage());
        }
    }

    /**
     * Clear old audit logs (keep only last 90 days by default)
     */
    public function clear(Request $request)
    {
        try {
            $daysToKeep = $request->get('days', 90);
            $cutoffDate = Carbon::now()->subDays($daysToKeep);

            $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Удалено $deletedCount записей старше $daysToKeep дней",
                    'deleted_count' => $deletedCount
                ]);
            }

            return redirect()->route('admin.audit-logs.index')
                ->with('success', "Удалено $deletedCount записей старше $daysToKeep дней");

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при очистке логов'
                ], 500);
            }

            return redirect()->back()->with('error', 'Ошибка при очистке логов: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics for dashboard
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

            // Логи по дням
            $logsByDay = AuditLog::select(
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

            // Логи по типам действий
            $logsByActionType = AuditLog::select(
                'action_type',
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('action_type')
                ->orderBy('count', 'desc')
                ->get();

            // Логи по пользователям (топ 10)
            $logsByUser = AuditLog::select(
                'user_id',
                DB::raw('COUNT(*) as count')
            )
                ->with('user')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Логи по моделям
            $logsByModel = AuditLog::select(
                'model_type',
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('model_type')
                ->groupBy('model_type')
                ->orderBy('count', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'logs_by_day' => $logsByDay,
                    'logs_by_action_type' => $logsByActionType,
                    'logs_by_user' => $logsByUser,
                    'logs_by_model' => $logsByModel,
                    'period' => $period,
                    'total_logs' => AuditLog::where('created_at', '>=', $startDate)->count()
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
     * Helper method to export to CSV
     */
    private function exportToCsv($logs)
    {
        $fileName = 'audit-logs-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($file, [
                'ID',
                'Дата',
                'Пользователь',
                'Администратор',
                'Тип действия',
                'Модель',
                'ID модели',
                'Описание',
                'Старые значения',
                'Новые значения',
                'IP адрес',
                'User Agent',
                'URL'
            ]);

            // Данные
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->email : 'Система',
                    $log->admin ? $log->admin->email : '-',
                    $this->getActionTypeLabel($log->action_type),
                    $log->model_type,
                    $log->model_id,
                    $log->description,
                    $log->old_values ? json_encode($log->old_values) : '-',
                    $log->new_values ? json_encode($log->new_values) : '-',
                    $log->ip_address,
                    $log->user_agent,
                    $log->url
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper method to get field label for display
     */
    private function getFieldLabel($field)
    {
        $labels = [
            'email' => 'Email',
            'name' => 'Имя',
            'password' => 'Пароль',
            'status' => 'Статус',
            'role' => 'Роль',
            'price' => 'Цена',
            'description' => 'Описание',
            'title' => 'Название',
            'content' => 'Содержание',
            'is_active' => 'Активность',
            'banned_until' => 'Заблокирован до',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Helper method to get action type label
     */
    private function getActionTypeLabel($actionType)
    {
        $labels = [
            'create' => 'Создание',
            'update' => 'Обновление',
            'delete' => 'Удаление',
            'login' => 'Вход',
            'logout' => 'Выход',
            'ban' => 'Блокировка',
            'unban' => 'Разблокировка',
            'payment' => 'Оплата',
            'booking' => 'Бронирование',
            'cancel' => 'Отмена',
            'confirm' => 'Подтверждение',
        ];

        return $labels[$actionType] ?? $actionType;
    }

    /**
     * Helper method to determine change type
     */
    private function getChangeType($old, $new)
    {
        if ($old === null && $new !== null) {
            return 'added';
        } elseif ($old !== null && $new === null) {
            return 'removed';
        } elseif ($old != $new) {
            return 'changed';
        }

        return 'unchanged';
    }
}
