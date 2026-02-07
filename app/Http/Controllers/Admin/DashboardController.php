<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Room;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Display the main admin dashboard with statistics.
     */
    public function index(): View
    {
        // Кэшируем основные статистические данные на 5 минут для производительности
        $stats = Cache::remember('admin_dashboard_stats', 300, function () {
            return $this->getDashboardStats();
        });

        // Получаем последние активности
        $recentActivities = $this->getRecentActivities();

        // Статистика по бронированиям за последние 30 дней
        $bookingStats = $this->getBookingChartData();

        // Доход по месяцам за текущий год
        $revenueStats = $this->getRevenueChartData();

        // Статусы номеров
        $roomStatuses = $this->getRoomStatusData();

        // Предстоящие заезды (ближайшие 7 дней)
        $upcomingCheckIns = $this->getUpcomingCheckIns();

        // Недавние отзывы
        $recentReviews = $this->getRecentReviews();

        return view('admin.dashboard.index', compact(
            'stats',
            'recentActivities',
            'bookingStats',
            'revenueStats',
            'roomStatuses',
            'upcomingCheckIns',
            'recentReviews'
        ));
    }

    /**
     * Get dashboard statistics.
     */
    private function getDashboardStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        return [
            // Пользователи
            'total_users' => User::count(),
            'new_users_today' => User::whereDate('created_at', $today)->count(),
            'new_users_month' => User::where('created_at', '>=', $thisMonth)->count(),
            'active_users' => User::whereHas('bookings', function ($query) use ($thisMonth) {
                $query->where('created_at', '>=', $thisMonth);
            })->count(),

            // Бронирования
            'total_bookings' => Booking::count(),
            'bookings_today' => Booking::whereDate('created_at', $today)->count(),
            'bookings_month' => Booking::where('created_at', '>=', $thisMonth)->count(),
            'active_bookings' => Booking::whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>=', $today)
                ->count(),

            // Номера
            'total_rooms' => Room::count(),
            'available_rooms' => Room::where('status', 'active')->count(),
            'occupied_rooms' => Booking::whereIn('status', ['pending', 'confirmed'])
                ->where('check_in', '<=', $today)
                ->where('check_out', '>=', $today)
                ->distinct('room_id')
                ->count('room_id'),

            // Платежи
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'revenue_today' => Payment::where('status', 'completed')
                ->whereDate('payment_date', $today)
                ->sum('amount'),
            'revenue_month' => Payment::where('status', 'completed')
                ->where('payment_date', '>=', $thisMonth)
                ->sum('amount'),
            'revenue_last_month' => Payment::where('status', 'completed')
                ->whereBetween('payment_date', [$lastMonth, $thisMonth])
                ->sum('amount'),

            // Отзывы
            'total_reviews' => Review::count(),
            'average_rating' => Review::avg('rating') ?? 0,
            'new_reviews_today' => Review::whereDate('created_at', $today)->count(),

            // Загрузка отеля
            'occupancy_rate' => $this->calculateOccupancyRate(),
        ];
    }

    /**
     * Calculate hotel occupancy rate.
     */
    private function calculateOccupancyRate(): float
    {
        $today = Carbon::today();
        $totalRooms = Room::where('status', 'active')->count();

        if ($totalRooms == 0) {
            return 0;
        }

        $occupiedRooms = Booking::whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '<=', $today)
            ->where('check_out', '>=', $today)
            ->distinct('room_id')
            ->count('room_id');

        return round(($occupiedRooms / $totalRooms) * 100, 2);
    }

    /**
     * Get recent activities for dashboard.
     */
    private function getRecentActivities(): array
    {
        $activities = [];

        // Последние бронирования
        $recentBookings = Booking::with(['user', 'room'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($booking) {
                return [
                    'type' => 'booking',
                    'icon' => 'calendar-check',
                    'color' => 'primary',
                    'title' => 'Новое бронирование',
                    'description' => "{$booking->user->name} забронировал номер #{$booking->room->id}",
                    'time' => $booking->created_at->diffForHumans(),
                    'link' => route('admin.bookings.show', $booking),
                ];
            });

        $activities = array_merge($activities, $recentBookings->toArray());

        // Последние регистрации
        $recentUsers = User::latest()
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user',
                    'icon' => 'user-plus',
                    'color' => 'success',
                    'title' => 'Новый пользователь',
                    'description' => "{$user->name} ({$user->email}) зарегистрировался",
                    'time' => $user->created_at->diffForHumans(),
                    'link' => route('admin.users.show', $user),
                ];
            });

        $activities = array_merge($activities, $recentUsers->toArray());

        // Последние платежи
        $recentPayments = Payment::with(['booking.user'])
            ->where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($payment) {
                return [
                    'type' => 'payment',
                    'icon' => 'credit-card',
                    'color' => 'warning',
                    'title' => 'Новый платеж',
                    'description' => "Оплата от {$payment->booking->user->name} на сумму {$payment->amount} руб.",
                    'time' => $payment->created_at->diffForHumans(),
                    'link' => route('admin.payments.show', $payment),
                ];
            });

        $activities = array_merge($activities, $recentPayments->toArray());

        // Сортируем по времени (самые свежие сначала)
        usort($activities, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 15); // Ограничиваем 15 последними активностями
    }

    /**
     * Get booking data for chart (last 30 days).
     */
    private function getBookingChartData(): array
    {
        $data = Booking::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Заполняем пропущенные дни нулями
        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $chartData[$date] = $data[$date] ?? 0;
        }

        return [
            'labels' => array_keys($chartData),
            'data' => array_values($chartData),
        ];
    }

    /**
     * Get revenue data for chart (current year by months).
     */
    private function getRevenueChartData(): array
    {
        $currentYear = Carbon::now()->year;

        $data = Payment::select(
            DB::raw('MONTH(payment_date) as month'),
            DB::raw('SUM(amount) as revenue')
        )
            ->where('status', 'completed')
            ->whereYear('payment_date', $currentYear)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('revenue', 'month')
            ->toArray();

        // Заполняем все месяцы
        $chartData = [];
        $monthNames = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];

        for ($month = 1; $month <= 12; $month++) {
            $chartData[$monthNames[$month - 1]] = $data[$month] ?? 0;
        }

        return [
            'labels' => array_keys($chartData),
            'data' => array_values($chartData),
            'total' => array_sum($data),
            'average' => count($data) > 0 ? array_sum($data) / count($data) : 0,
        ];
    }

    /**
     * Get room status data for pie chart.
     */
    private function getRoomStatusData(): array
    {
        $totalRooms = Room::count();
        $available = Room::where('status', 'active')->count();
        $maintenance = Room::where('status', 'maintenance')->count();
        $inactive = Room::where('status', 'inactive')->count();

        // Занятые номера (сейчас)
        $today = Carbon::today();
        $occupied = Booking::whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '<=', $today)
            ->where('check_out', '>=', $today)
            ->distinct('room_id')
            ->count('room_id');

        return [
            'total' => $totalRooms,
            'available' => $available - $occupied, // Свободные = активные - занятые
            'occupied' => $occupied,
            'maintenance' => $maintenance,
            'inactive' => $inactive,
        ];
    }

    /**
     * Get upcoming check-ins (next 7 days).
     */
    private function getUpcomingCheckIns()
    {
        return Booking::with(['user', 'room'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('check_in', [Carbon::today(), Carbon::today()->addDays(7)])
            ->orderBy('check_in')
            ->limit(10)
            ->get();
    }

    /**
     * Get recent reviews.
     */
    private function getRecentReviews()
    {
        return Review::with(['user', 'booking.room'])
            ->where('status', 'approved')
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * Refresh dashboard statistics (AJAX endpoint).
     */
    public function refreshStats(Request $request)
    {
        if (!$request->ajax()) {
            abort(403);
        }

        // Очищаем кэш
        Cache::forget('admin_dashboard_stats');

        $stats = $this->getDashboardStats();
        $occupancyRate = $this->calculateOccupancyRate();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'occupancy_rate' => $occupancyRate,
            'updated_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Get quick stats for header widget.
     */
    public function quickStats()
    {
        $today = Carbon::today();

        $stats = [
            'bookings_today' => Booking::whereDate('created_at', $today)->count(),
            'revenue_today' => Payment::where('status', 'completed')
                ->whereDate('payment_date', $today)
                ->sum('amount'),
            'new_users_today' => User::whereDate('created_at', $today)->count(),
            'unread_messages' => 0, // Будет из модели ChatMessage
        ];

        return response()->json($stats);
    }

    /**
     * Display system health check page.
     */
    public function systemHealth(): View
    {
        $health = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk_space' => $this->getDiskSpace(),
            'server_load' => $this->getServerLoad(),
            'last_backup' => $this->getLastBackupDate(),
        ];

        return view('admin.dashboard.health', compact('health'));
    }

    /**
     * Check database connection.
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Подключение к базе данных активно',
                'details' => [
                    'driver' => DB::connection()->getDriverName(),
                    'version' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Ошибка подключения к базе данных: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage permissions.
     */
    private function checkStorage(): array
    {
        $paths = [
            storage_path(),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        $issues = [];
        foreach ($paths as $path) {
            if (!is_writable($path)) {
                $issues[] = "Нет прав на запись в: " . str_replace(base_path(), '', $path);
            }
        }

        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'Все пути для хранения данных доступны для записи',
            ];
        }

        return [
            'status' => 'warning',
            'message' => 'Обнаружены проблемы с правами доступа',
            'details' => $issues,
        ];
    }

    /**
     * Check cache status.
     */
    private function checkCache(): array
    {
        try {
            Cache::put('health_check', 'test', 10);
            $value = Cache::get('health_check');

            if ($value === 'test') {
                return [
                    'status' => 'healthy',
                    'message' => 'Кэш-система работает корректно',
                    'details' => [
                        'driver' => config('cache.default'),
                    ]
                ];
            }
        } catch (\Exception $e) {
            // ignore
        }

        return [
            'status' => 'error',
            'message' => 'Проблемы с кэш-системой',
        ];
    }

    /**
     * Get disk space information.
     */
    private function getDiskSpace(): array
    {
        $free = disk_free_space(base_path());
        $total = disk_total_space(base_path());
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 2) : 0;

        $status = 'healthy';
        if ($percent > 90) {
            $status = 'error';
        } elseif ($percent > 75) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'percent' => $percent,
            'free' => $this->formatBytes($free),
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Check queue status.
     */
    private function checkQueue(): array
    {
        // Для простоты проверяем наличие таблицы jobs
        try {
            $queueCount = DB::table('jobs')->count();

            return [
                'status' => $queueCount > 100 ? 'warning' : 'healthy',
                'message' => "Очередь задач: {$queueCount} заданий в ожидании",
                'count' => $queueCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'info',
                'message' => 'Таблица очередей не настроена',
            ];
        }
    }

    /**
     * Get server load (Unix only).
     */
    private function getServerLoad(): array
    {
        if (!function_exists('sys_getloadavg')) {
            return [
                'status' => 'info',
                'message' => 'Информация о нагрузке недоступна',
            ];
        }

        $load = sys_getloadavg();
        $cores = intval(shell_exec('nproc') ?: 1);
        $loadPercent = ($load[0] / $cores) * 100;

        $status = 'healthy';
        if ($loadPercent > 90) {
            $status = 'error';
        } elseif ($loadPercent > 70) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'load_1min' => $load[0],
            'load_5min' => $load[1],
            'load_15min' => $load[2],
            'cores' => $cores,
            'percent' => round($loadPercent, 2),
        ];
    }

    /**
     * Get last backup date.
     */
    private function getLastBackupDate(): array
    {
        $backupPath = storage_path('app/backups');

        if (!file_exists($backupPath)) {
            return [
                'status' => 'warning',
                'message' => 'Папка для бэкапов не существует',
            ];
        }

        $files = glob($backupPath . '/*.zip');

        if (empty($files)) {
            return [
                'status' => 'warning',
                'message' => 'Бэкапы не найдены',
            ];
        }

        $latestFile = max($files);
        $lastModified = filemtime($latestFile);
        $daysAgo = floor((time() - $lastModified) / (60 * 60 * 24));

        $status = 'healthy';
        if ($daysAgo > 7) {
            $status = 'error';
        } elseif ($daysAgo > 3) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'message' => "Последний бэкап: " . date('d.m.Y H:i', $lastModified),
            'days_ago' => $daysAgo,
            'file' => basename($latestFile),
        ];
    }
}
