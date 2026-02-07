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
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class StatisticsController extends Controller
{
    /**
     * Display main statistics dashboard.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Период по умолчанию - последние 30 дней
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Основные KPI
        $kpis = $this->getKpis($dateFrom, $dateTo);

        // Графики
        $charts = $this->getCharts($dateFrom, $dateTo);

        // Топ статистики
        $topStats = $this->getTopStatistics($dateFrom, $dateTo);

        // Сравнение с предыдущим периодом
        $comparison = $this->getPeriodComparison($dateFrom, $dateTo);

        return view('admin.statistics.index', compact(
            'kpis',
            'charts',
            'topStats',
            'comparison',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Get KPI statistics.
     */
    private function getKpis(string $dateFrom, string $dateTo): array
    {
        return Cache::remember("kpis_{$dateFrom}_{$dateTo}", 300, function () use ($dateFrom, $dateTo) {
            // Общая выручка
            $revenue = Payment::where('status', 'completed')
                ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                ->sum('amount');

            // Количество бронирований
            $bookings = Booking::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count();

            // Количество пользователей
            $users = User::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->where('role', 'user')
                ->count();

            // Средний чек
            $avgCheck = $bookings > 0
                ? Payment::where('status', 'completed')
                    ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->avg('amount')
                : 0;

            // Загрузка отеля
            $occupancyRate = $this->calculateOccupancyRate($dateFrom, $dateTo);

            // Коэффициент конверсии
            $conversionRate = $this->calculateConversionRate($dateFrom, $dateTo);

            // Средний рейтинг
            $avgRating = Review::where('status', 'approved')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->avg('rating') ?? 0;

            // Возвраты
            $refunds = Payment::where('status', 'refunded')
                ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                ->sum('amount');

            return [
                'revenue' => [
                    'value' => $revenue,
                    'formatted' => number_format($revenue, 0, '.', ' ') . ' ₽',
                    'icon' => 'currency-rub',
                    'color' => 'success',
                    'label' => 'Выручка',
                ],
                'bookings' => [
                    'value' => $bookings,
                    'formatted' => number_format($bookings, 0, '.', ' '),
                    'icon' => 'calendar-check',
                    'color' => 'primary',
                    'label' => 'Бронирований',
                ],
                'users' => [
                    'value' => $users,
                    'formatted' => number_format($users, 0, '.', ' '),
                    'icon' => 'users',
                    'color' => 'info',
                    'label' => 'Новых пользователей',
                ],
                'avg_check' => [
                    'value' => $avgCheck,
                    'formatted' => number_format($avgCheck, 0, '.', ' ') . ' ₽',
                    'icon' => 'receipt',
                    'color' => 'warning',
                    'label' => 'Средний чек',
                ],
                'occupancy_rate' => [
                    'value' => $occupancyRate,
                    'formatted' => number_format($occupancyRate, 1, '.', ' ') . ' %',
                    'icon' => 'building',
                    'color' => 'secondary',
                    'label' => 'Загрузка отеля',
                ],
                'conversion_rate' => [
                    'value' => $conversionRate,
                    'formatted' => number_format($conversionRate, 1, '.', ' ') . ' %',
                    'icon' => 'trending-up',
                    'color' => 'danger',
                    'label' => 'Конверсия',
                ],
                'avg_rating' => [
                    'value' => $avgRating,
                    'formatted' => number_format($avgRating, 1, '.', ' '),
                    'icon' => 'star',
                    'color' => 'warning',
                    'label' => 'Средний рейтинг',
                ],
                'refunds' => [
                    'value' => $refunds,
                    'formatted' => number_format($refunds, 0, '.', ' ') . ' ₽',
                    'icon' => 'arrow-counterclockwise',
                    'color' => 'dark',
                    'label' => 'Возвраты',
                ],
            ];
        });
    }

    /**
     * Calculate occupancy rate.
     */
    private function calculateOccupancyRate(string $dateFrom, string $dateTo): float
    {
        $totalRooms = Room::where('status', 'active')->count();

        if ($totalRooms === 0) {
            return 0;
        }

        // Количество дней в периоде
        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        $totalDays = $start->diffInDays($end) + 1;

        // Максимально возможное количество занятых номеро-дней
        $maxRoomDays = $totalRooms * $totalDays;

        // Фактически занятые номеро-дни
        $bookedRoomDays = Booking::whereIn('status', ['confirmed', 'completed'])
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('check_in', [$dateFrom, $dateTo])
                    ->orWhereBetween('check_out', [$dateFrom, $dateTo])
                    ->orWhere(function ($q) use ($dateFrom, $dateTo) {
                        $q->where('check_in', '<', $dateFrom)
                            ->where('check_out', '>', $dateTo);
                    });
            })
            ->get()
            ->sum(function ($booking) use ($dateFrom, $dateTo) {
                $checkIn = max($booking->check_in, Carbon::parse($dateFrom));
                $checkOut = min($booking->check_out, Carbon::parse($dateTo));
                return $checkIn->diffInDays($checkOut);
            });

        return $maxRoomDays > 0 ? round(($bookedRoomDays / $maxRoomDays) * 100, 2) : 0;
    }

    /**
     * Calculate conversion rate.
     */
    private function calculateConversionRate(string $dateFrom, string $dateTo): float
    {
        // Количество посещений (здесь можно интегрировать с Google Analytics)
        $visits = Cache::remember("visits_{$dateFrom}_{$dateTo}", 3600, function () use ($dateFrom, $dateTo) {
            // Заглушка - в реальном проекте здесь будет интеграция с аналитикой
            return rand(500, 2000);
        });

        // Количество бронирований
        $bookings = Booking::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->count();

        return $visits > 0 ? round(($bookings / $visits) * 100, 2) : 0;
    }

    /**
     * Get charts data.
     */
    private function getCharts(string $dateFrom, string $dateTo): array
    {
        return Cache::remember("charts_{$dateFrom}_{$dateTo}", 300, function () use ($dateFrom, $dateTo) {
            // Ежедневная выручка
            $revenueByDay = Payment::where('status', 'completed')
                ->select(
                    DB::raw('DATE(payment_date) as date'),
                    DB::raw('SUM(amount) as revenue')
                )
                ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Бронирования по дням
            $bookingsByDay = Booking::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Статусы бронирований
            $bookingStatuses = Booking::select('status', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            // Платежные системы
            $paymentSystems = Payment::where('status', 'completed')
                ->select('payment_system', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as revenue'))
                ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('payment_system')
                ->get();

            // Рейтинги отзывов
            $reviewRatings = Review::where('status', 'approved')
                ->select('rating', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('rating')
                ->orderBy('rating')
                ->get();

            // Регистрации пользователей по дням
            $registrationsByDay = User::where('role', 'user')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return [
                'revenue_by_day' => [
                    'labels' => $revenueByDay->pluck('date'),
                    'data' => $revenueByDay->pluck('revenue'),
                ],
                'bookings_by_day' => [
                    'labels' => $bookingsByDay->pluck('date'),
                    'data' => $bookingsByDay->pluck('count'),
                ],
                'booking_statuses' => [
                    'labels' => $bookingStatuses->keys(),
                    'data' => $bookingStatuses->values(),
                ],
                'payment_systems' => [
                    'labels' => $paymentSystems->pluck('payment_system'),
                    'counts' => $paymentSystems->pluck('count'),
                    'revenues' => $paymentSystems->pluck('revenue'),
                ],
                'review_ratings' => [
                    'labels' => $reviewRatings->pluck('rating')->map(fn($r) => "$r звезд"),
                    'data' => $reviewRatings->pluck('count'),
                ],
                'registrations_by_day' => [
                    'labels' => $registrationsByDay->pluck('date'),
                    'data' => $registrationsByDay->pluck('count'),
                ],
            ];
        });
    }

    /**
     * Get top statistics.
     */
    private function getTopStatistics(string $dateFrom, string $dateTo): array
    {
        return [
            // Самые популярные номера
            'popular_rooms' => Room::withCount(['bookings' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            }])
                ->withSum(['bookings' => function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
                }], 'total_price')
                ->orderBy('bookings_count', 'desc')
                ->limit(5)
                ->get(),

            // Самые активные пользователи
            'active_users' => User::withCount(['bookings' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            }])
                ->withSum(['bookings' => function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
                }], 'total_price')
                ->where('role', 'user')
                ->orderBy('bookings_count', 'desc')
                ->limit(5)
                ->get(),

            // Самые частые посетители сайта (заглушка)
            'frequent_visitors' => collect([]),

            // Самые популярные даты заезда
            'popular_checkin_dates' => Booking::select(
                DB::raw('DATE(check_in) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->whereBetween('check_in', [$dateFrom, $dateTo . ' 23:59:59'])
                ->groupBy('date')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get(),

            // Самые дорогие бронирования
            'most_expensive_bookings' => Booking::with(['user', 'room'])
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->orderBy('total_price', 'desc')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Compare with previous period.
     */
    private function getPeriodComparison(string $dateFrom, string $dateTo): array
    {
        $currentStart = Carbon::parse($dateFrom);
        $currentEnd = Carbon::parse($dateTo);

        // Предыдущий период такой же длины
        $periodLength = $currentStart->diffInDays($currentEnd);
        $previousStart = $currentStart->copy()->subDays($periodLength + 1);
        $previousEnd = $currentStart->copy()->subDay();

        $currentPeriod = [
            'start' => $currentStart->format('Y-m-d'),
            'end' => $currentEnd->format('Y-m-d'),
        ];

        $previousPeriod = [
            'start' => $previousStart->format('Y-m-d'),
            'end' => $previousEnd->format('Y-m-d'),
        ];

        // Сравниваем ключевые метрики
        $metrics = ['revenue', 'bookings', 'users', 'avg_check'];

        $comparison = [];
        foreach ($metrics as $metric) {
            $currentValue = $this->getMetricValue($metric, $currentPeriod['start'], $currentPeriod['end']);
            $previousValue = $this->getMetricValue($metric, $previousPeriod['start'], $previousPeriod['end']);

            $change = $previousValue > 0
                ? (($currentValue - $previousValue) / $previousValue) * 100
                : ($currentValue > 0 ? 100 : 0);

            $comparison[$metric] = [
                'current' => $currentValue,
                'previous' => $previousValue,
                'change' => round($change, 2),
                'trend' => $change >= 0 ? 'up' : 'down',
            ];
        }

        return [
            'current_period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'metrics' => $comparison,
        ];
    }

    /**
     * Get metric value for period.
     */
    private function getMetricValue(string $metric, string $dateFrom, string $dateTo)
    {
        switch ($metric) {
            case 'revenue':
                return Payment::where('status', 'completed')
                    ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->sum('amount');

            case 'bookings':
                return Booking::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->count();

            case 'users':
                return User::where('role', 'user')
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->count();

            case 'avg_check':
                $revenue = $this->getMetricValue('revenue', $dateFrom, $dateTo);
                $bookings = $this->getMetricValue('bookings', $dateFrom, $dateTo);
                return $bookings > 0 ? $revenue / $bookings : 0;

            default:
                return 0;
        }
    }

    /**
     * Display booking statistics.
     */
    public function bookings(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Общая статистика бронирований
        $totalStats = Booking::select(
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
            DB::raw('SUM(total_price) as total_revenue'),
            DB::raw('AVG(total_price) as avg_price'),
            DB::raw('AVG(TIMESTAMPDIFF(DAY, check_in, check_out)) as avg_stay_length')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        // Бронирования по месяцам
        $monthlyStats = Booking::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total_price) as revenue')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Бронирования по дням недели
        $weekdayStats = Booking::select(
            DB::raw('DAYOFWEEK(created_at) as weekday'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('weekday')
            ->orderBy('weekday')
            ->get();

        // Длина пребывания
        $stayLengthStats = Booking::select(
            DB::raw('TIMESTAMPDIFF(DAY, check_in, check_out) as days'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->where('status', 'completed')
            ->groupBy('days')
            ->orderBy('days')
            ->get();

        // Бронирования по количеству гостей
        $guestsStats = Booking::select(
            'guests_count',
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->where('status', 'completed')
            ->groupBy('guests_count')
            ->orderBy('guests_count')
            ->get();

        return view('admin.statistics.bookings', compact(
            'totalStats',
            'monthlyStats',
            'weekdayStats',
            'stayLengthStats',
            'guestsStats',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Display revenue statistics.
     */
    public function revenue(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Общая статистика выручки
        $totalStats = Payment::where('status', 'completed')
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(amount) as avg_amount'),
                DB::raw('MAX(amount) as max_amount'),
                DB::raw('MIN(amount) as min_amount')
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        // Выручка по месяцам
        $monthlyRevenue = Payment::where('status', 'completed')
            ->select(
                DB::raw('YEAR(payment_date) as year'),
                DB::raw('MONTH(payment_date) as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Выручка по платежным системам
        $paymentSystems = Payment::where('status', 'completed')
            ->select(
                'payment_system',
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(amount) as avg_amount')
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('payment_system')
            ->orderBy('revenue', 'desc')
            ->get();

        // Ежедневная выручка
        $dailyRevenue = Payment::where('status', 'completed')
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Возвраты
        $refunds = Payment::where('status', 'refunded')
            ->select(
                DB::raw('SUM(amount) as total_refunded'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        return view('admin.statistics.revenue', compact(
            'totalStats',
            'monthlyRevenue',
            'paymentSystems',
            'dailyRevenue',
            'refunds',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Display user statistics.
     */
    public function users(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Общая статистика пользователей
        $totalStats = User::where('role', 'user')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active'),
                DB::raw('SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) as inactive'),
                DB::raw('SUM(CASE WHEN banned_until IS NOT NULL AND banned_until > NOW() THEN 1 ELSE 0 END) as banned'),
                DB::raw('AVG(TIMESTAMPDIFF(DAY, created_at, COALESCE(last_login_at, NOW()))) as avg_days_since_registration')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        // Регистрации по дням
        $registrationsByDay = User::where('role', 'user')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Активность пользователей
        $userActivity = User::where('role', 'user')
            ->withCount(['bookings', 'reviews'])
            ->whereHas('bookings')
            ->orderBy('bookings_count', 'desc')
            ->limit(10)
            ->get();

        // География пользователей (если есть данные)
        $userGeography = User::where('role', 'user')
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Время суток регистрации
        $registrationHours = User::where('role', 'user')
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return view('admin.statistics.users', compact(
            'totalStats',
            'registrationsByDay',
            'userActivity',
            'userGeography',
            'registrationHours',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Display room statistics.
     */
    public function rooms(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Статистика по номерам
        $roomStats = Room::withCount(['bookings' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
        }])
            ->withSum(['bookings' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            }], 'total_price')
            ->withAvg(['reviews' => function ($query) {
                $query->where('status', 'approved');
            }], 'rating')
            ->orderBy('bookings_count', 'desc')
            ->get();

        // Статистика по типам номеров
        $typeStats = DB::table('rooms')
            ->join('room_types', 'rooms.type_id', '=', 'room_types.id')
            ->leftJoin('bookings', function ($join) use ($dateFrom, $dateTo) {
                $join->on('rooms.id', '=', 'bookings.room_id')
                    ->whereBetween('bookings.created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            })
            ->select(
                'room_types.name',
                'room_types.id',
                DB::raw('COUNT(DISTINCT rooms.id) as room_count'),
                DB::raw('COUNT(DISTINCT bookings.id) as booking_count'),
                DB::raw('SUM(bookings.total_price) as total_revenue'),
                DB::raw('AVG(rooms.price_per_night) as avg_price')
            )
            ->groupBy('room_types.id', 'room_types.name')
            ->orderBy('booking_count', 'desc')
            ->get();

        // Загрузка по дням
        $occupancyByDay = $this->getDailyOccupancy($dateFrom, $dateTo);

        // Популярные удобства
        $popularAmenities = DB::table('amenity_room')
            ->join('amenities', 'amenity_room.amenity_id', '=', 'amenities.id')
            ->join('rooms', 'amenity_room.room_id', '=', 'rooms.id')
            ->join('bookings', function ($join) use ($dateFrom, $dateTo) {
                $join->on('rooms.id', '=', 'bookings.room_id')
                    ->whereBetween('bookings.created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            })
            ->select(
                'amenities.name',
                'amenities.id',
                DB::raw('COUNT(DISTINCT bookings.id) as booking_count'),
                DB::raw('SUM(bookings.total_price) as total_revenue')
            )
            ->groupBy('amenities.id', 'amenities.name')
            ->orderBy('booking_count', 'desc')
            ->limit(10)
            ->get();

        return view('admin.statistics.rooms', compact(
            'roomStats',
            'typeStats',
            'occupancyByDay',
            'popularAmenities',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Get daily occupancy statistics.
     */
    private function getDailyOccupancy(string $dateFrom, string $dateTo)
    {
        $totalRooms = Room::where('status', 'active')->count();

        if ($totalRooms === 0) {
            return collect();
        }

        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);

        $results = collect();

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            $occupiedRooms = Booking::whereIn('status', ['confirmed', 'completed'])
                ->whereDate('check_in', '<=', $dateStr)
                ->whereDate('check_out', '>', $dateStr)
                ->distinct('room_id')
                ->count('room_id');

            $occupancyRate = $totalRooms > 0
                ? round(($occupiedRooms / $totalRooms) * 100, 2)
                : 0;

            $results->push([
                'date' => $dateStr,
                'occupied' => $occupiedRooms,
                'available' => $totalRooms - $occupiedRooms,
                'rate' => $occupancyRate,
            ]);
        }

        return $results;
    }

    /**
     * Display marketing statistics.
     */
    public function marketing(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Статистика уведомлений
        $notificationStats = Notification::select(
            'channel',
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent'),
            DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
            DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('channel')
            ->get();

        // Эффективность рассылок
        $campaignStats = Notification::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as sent'),
            DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
            DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Конверсия по источникам трафика (заглушка)
        $trafficSources = [
            ['source' => 'Поисковики', 'visits' => 1250, 'conversions' => 45, 'rate' => 3.6],
            ['source' => 'Соц. сети', 'visits' => 850, 'conversions' => 32, 'rate' => 3.8],
            ['source' => 'Прямые заходы', 'visits' => 620, 'conversions' => 28, 'rate' => 4.5],
            ['source' => 'Реклама', 'visits' => 430, 'conversions' => 18, 'rate' => 4.2],
            ['source' => 'Рефералы', 'visits' => 210, 'conversions' => 12, 'rate' => 5.7],
        ];

        // Эффективность скидок
        $discountStats = DB::table('discounts')
            ->leftJoin('bookings', 'discounts.id', '=', 'bookings.discount_id')
            ->select(
                'discounts.code',
                'discounts.name',
                DB::raw('COUNT(bookings.id) as usage_count'),
                DB::raw('SUM(bookings.discount_amount) as total_discount'),
                DB::raw('SUM(bookings.total_price) as total_revenue')
            )
            ->whereBetween('bookings.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->orWhereNull('bookings.id')
            ->groupBy('discounts.id', 'discounts.code', 'discounts.name')
            ->orderBy('usage_count', 'desc')
            ->get();

        // ROI кампаний (заглушка)
        $roiStats = [
            ['campaign' => 'Летняя распродажа', 'cost' => 50000, 'revenue' => 250000, 'roi' => 400],
            ['campaign' => 'Раннее бронирование', 'cost' => 30000, 'revenue' => 180000, 'roi' => 500],
            ['campaign' => 'Скидка новым клиентам', 'cost' => 20000, 'revenue' => 120000, 'roi' => 500],
            ['campaign' => 'Праздничные предложения', 'cost' => 40000, 'revenue' => 220000, 'roi' => 450],
        ];

        return view('admin.statistics.marketing', compact(
            'notificationStats',
            'campaignStats',
            'trafficSources',
            'discountStats',
            'roiStats',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Display real-time statistics.
     */
    public function realtime(): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Текущие бронирования
        $currentBookings = Booking::with(['user', 'room'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) {
                $query->whereDate('check_in', '<=', now())
                    ->whereDate('check_out', '>=', now());
            })
            ->orderBy('check_in')
            ->limit(10)
            ->get();

        // Сегодняшние заезды
        $todayCheckins = Booking::with(['user', 'room'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('check_in', today())
            ->orderBy('check_in')
            ->limit(10)
            ->get();

        // Сегодняшние выезды
        $todayCheckouts = Booking::with(['user', 'room'])
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->whereDate('check_out', today())
            ->orderBy('check_out')
            ->limit(10)
            ->get();

        // Активные пользователи онлайн (заглушка)
        $onlineUsers = User::where('role', 'user')
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subMinutes(30))
            ->orderBy('last_login_at', 'desc')
            ->limit(10)
            ->get();

        // Последние платежи
        $recentPayments = Payment::with(['booking.user'])
            ->where('status', 'completed')
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get();

        // Последние отзывы
        $recentReviews = Review::with(['user', 'booking.room'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.statistics.realtime', compact(
            'currentBookings',
            'todayCheckins',
            'todayCheckouts',
            'onlineUsers',
            'recentPayments',
            'recentReviews'
        ));
    }

    /**
     * Export statistics data.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-statistics')) {
            abort(403);
        }

        $type = $request->get('type', 'general');
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $fileName = "statistics_{$type}_{$dateFrom}_{$dateTo}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($type, $dateFrom, $dateTo) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            switch ($type) {
                case 'bookings':
                    $this->exportBookingsData($file, $dateFrom, $dateTo);
                    break;

                case 'revenue':
                    $this->exportRevenueData($file, $dateFrom, $dateTo);
                    break;

                case 'users':
                    $this->exportUsersData($file, $dateFrom, $dateTo);
                    break;

                case 'rooms':
                    $this->exportRoomsData($file, $dateFrom, $dateTo);
                    break;

                default:
                    $this->exportGeneralData($file, $dateFrom, $dateTo);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export general statistics data.
     */
    private function exportGeneralData($file, string $dateFrom, string $dateTo): void
    {
        fputcsv($file, ['Отчет по статистике', "Период: {$dateFrom} - {$dateTo}"], ';');
        fputcsv($file, []);

        // KPI
        $kpis = $this->getKpis($dateFrom, $dateTo);

        fputcsv($file, ['Ключевые показатели'], ';');
        fputcsv($file, ['Показатель', 'Значение'], ';');

        foreach ($kpis as $label => $data) {
            fputcsv($file, [$data['label'], $data['formatted']], ';');
        }

        fputcsv($file, []);
    }

    /**
     * Export bookings statistics data.
     */
    private function exportBookingsData($file, string $dateFrom, string $dateTo): void
    {
        $stats = Booking::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total_price) as revenue'),
            DB::raw('AVG(total_price) as avg_price'),
            DB::raw('AVG(TIMESTAMPDIFF(DAY, check_in, check_out)) as avg_stay')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        fputcsv($file, ['Статистика бронирований', "Период: {$dateFrom} - {$dateTo}"], ';');
        fputcsv($file, ['Дата', 'Количество', 'Выручка', 'Средний чек', 'Средняя длительность (дни)'], ';');

        foreach ($stats as $stat) {
            fputcsv($file, [
                $stat->date,
                $stat->count,
                number_format($stat->revenue ?? 0, 2, '.', ''),
                number_format($stat->avg_price ?? 0, 2, '.', ''),
                number_format($stat->avg_stay ?? 0, 1, '.', ''),
            ], ';');
        }
    }

    /**
     * Export revenue statistics data.
     */
    private function exportRevenueData($file, string $dateFrom, string $dateTo): void
    {
        $stats = Payment::where('status', 'completed')
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('AVG(amount) as avg_amount'),
                'payment_system'
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('date', 'payment_system')
            ->orderBy('date')
            ->orderBy('payment_system')
            ->get();

        fputcsv($file, ['Статистика выручки', "Период: {$dateFrom} - {$dateTo}"], ';');
        fputcsv($file, ['Дата', 'Платежная система', 'Количество', 'Выручка', 'Средний чек'], ';');

        foreach ($stats as $stat) {
            fputcsv($file, [
                $stat->date,
                $stat->payment_system,
                $stat->count,
                number_format($stat->revenue ?? 0, 2, '.', ''),
                number_format($stat->avg_amount ?? 0, 2, '.', ''),
            ], ';');
        }
    }

    /**
     * Get statistics data for API.
     */
    public function apiData(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $metric = $request->get('metric', 'revenue');

        $data = $this->getMetricData($metric, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
        ]);
    }

    /**
     * Get metric data for API.
     */
    private function getMetricData(string $metric, string $dateFrom, string $dateTo): array
    {
        switch ($metric) {
            case 'revenue_daily':
                return Payment::where('status', 'completed')
                    ->select(
                        DB::raw('DATE(payment_date) as date'),
                        DB::raw('SUM(amount) as value')
                    )
                    ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => $item->date,
                            'value' => (float)$item->value,
                        ];
                    })
                    ->toArray();

            case 'bookings_daily':
                return Booking::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as value')
                )
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => $item->date,
                            'value' => (int)$item->value,
                        ];
                    })
                    ->toArray();

            case 'users_daily':
                return User::where('role', 'user')
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(*) as value')
                    )
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => $item->date,
                            'value' => (int)$item->value,
                        ];
                    })
                    ->toArray();

            case 'occupancy_daily':
                $data = $this->getDailyOccupancy($dateFrom, $dateTo);
                return $data->map(function ($item) {
                    return [
                        'date' => $item['date'],
                        'value' => $item['rate'],
                    ];
                })->toArray();

            default:
                return [];
        }
    }

    /**
     * Clear statistics cache.
     */
    public function clearCache(): RedirectResponse
    {
        if (!Gate::allows('manage-statistics')) {
            abort(403);
        }

        // Очищаем кэш статистики
        Cache::tags(['statistics'])->flush();

        return back()->with('success', 'Кэш статистики очищен.');
    }
}
