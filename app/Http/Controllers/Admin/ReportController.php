<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Room;
use App\Exports\BookingsExport;
use App\Exports\PaymentsExport;
use App\Exports\UsersExport;
use App\Exports\RoomsExport;
use App\Exports\FinancialReportExport;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Главная страница отчетов
     *
     * @return View
     */
    public function index(): View
    {
        // Быстрая статистика для выбора отчетов
        $quickStats = Cache::remember('report_quick_stats', 300, function () {
            return [
                'today_bookings' => Booking::whereDate('created_at', today())->count(),
                'month_revenue' => Payment::whereMonth('payment_date', now()->month)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'active_users' => User::where('status', 'active')->count(),
                'available_rooms' => Room::where('status', 'active')->count(),
            ];
        });

        $reportTypes = [
            'bookings' => 'Бронирования',
            'payments' => 'Платежи',
            'users' => 'Пользователи',
            'rooms' => 'Номера',
            'financial' => 'Финансовый отчет',
            'occupancy' => 'Загрузка номеров',
            'revenue' => 'Выручка',
        ];

        $exportFormats = [
            'pdf' => 'PDF',
            'excel' => 'Excel',
            'csv' => 'CSV',
        ];

        return view('admin.reports.index', compact('quickStats', 'reportTypes', 'exportFormats'));
    }

    /**
     * Предпросмотр отчета
     *
     * @param ReportRequest $request
     * @return View|JsonResponse
     */
    public function preview(ReportRequest $request)
    {
        $validated = $request->validated();
        $reportType = $validated['report_type'];

        // Формируем данные для предпросмотра
        $data = $this->getReportData($reportType, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $data,
                'report_type' => $reportType,
                'filters' => $validated
            ]);
        }

        return view('admin.reports.preview', [
            'data' => $data,
            'reportType' => $reportType,
            'filters' => $validated,
            'total' => $this->calculateTotals($data, $reportType)
        ]);
    }

    /**
     * Экспорт отчета
     *
     * @param ReportRequest $request
     * @return mixed
     */
    public function export(ReportRequest $request)
    {
        $validated = $request->validated();
        $reportType = $validated['report_type'];
        $format = $validated['export_format'] ?? 'excel';
        $filename = $this->generateFilename($reportType, $format, $validated);

        switch ($reportType) {
            case 'bookings':
                $export = new BookingsExport($validated);
                break;
            case 'payments':
                $export = new PaymentsExport($validated);
                break;
            case 'users':
                $export = new UsersExport($validated);
                break;
            case 'rooms':
                $export = new RoomsExport($validated);
                break;
            case 'financial':
                $export = new FinancialReportExport($validated);
                break;
            default:
                abort(400, 'Неизвестный тип отчета');
        }

        switch ($format) {
            case 'excel':
                return Excel::download($export, $filename);
            case 'csv':
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            case 'pdf':
                return $this->exportToPdf($export, $filename, $validated);
            default:
                abort(400, 'Неизвестный формат экспорта');
        }
    }

    /**
     * Генерация PDF отчета
     *
     * @param $export
     * @param string $filename
     * @param array $filters
     * @return mixed
     */
    private function exportToPdf($export, string $filename, array $filters)
    {
        $data = $export->getData();
        $reportType = $filters['report_type'];

        $pdf = Pdf::loadView('admin.reports.pdf.' . $reportType, [
            'data' => $data,
            'filters' => $filters,
            'generated_at' => now()->format('d.m.Y H:i:s'),
            'totals' => $this->calculateTotals($data, $reportType)
        ]);

        return $pdf->download($filename);
    }

    /**
     * Получить данные для отчета
     *
     * @param string $reportType
     * @param array $filters
     * @return array
     */
    private function getReportData(string $reportType, array $filters): array
    {
        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])
            : now()->startOfMonth();

        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])
            : now()->endOfMonth();

        switch ($reportType) {
            case 'bookings':
                return $this->getBookingsReport($startDate, $endDate, $filters);
            case 'payments':
                return $this->getPaymentsReport($startDate, $endDate, $filters);
            case 'users':
                return $this->getUsersReport($startDate, $endDate, $filters);
            case 'rooms':
                return $this->getRoomsReport($startDate, $endDate, $filters);
            case 'financial':
                return $this->getFinancialReport($startDate, $endDate, $filters);
            case 'occupancy':
                return $this->getOccupancyReport($startDate, $endDate, $filters);
            case 'revenue':
                return $this->getRevenueReport($startDate, $endDate, $filters);
            default:
                return [];
        }
    }

    /**
     * Отчет по бронированиям
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getBookingsReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = Booking::with(['user', 'room', 'hotel'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['room_type'])) {
            $query->whereHas('room', function ($q) use ($filters) {
                $q->where('type', $filters['room_type']);
            });
        }

        if (!empty($filters['hotel_id'])) {
            $query->where('hotel_id', $filters['hotel_id']);
        }

        $bookings = $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 50);

        return [
            'bookings' => $bookings,
            'summary' => [
                'total' => $bookings->total(),
                'total_amount' => $bookings->sum('total_price'),
                'average_amount' => $bookings->avg('total_price'),
                'by_status' => $query->clone()
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
            ]
        ];
    }

    /**
     * Отчет по платежам
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getPaymentsReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = Payment::with(['booking.user', 'booking.room'])
            ->whereBetween('payment_date', [$startDate, $endDate]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('method', $filters['payment_method']);
        }

        $payments = $query->orderBy('payment_date', 'desc')
            ->paginate($filters['per_page'] ?? 50);

        return [
            'payments' => $payments,
            'summary' => [
                'total' => $payments->total(),
                'total_amount' => $payments->sum('amount'),
                'average_amount' => $payments->avg('amount'),
                'by_method' => $query->clone()
                    ->selectRaw('method, COUNT(*) as count, SUM(amount) as total')
                    ->groupBy('method')
                    ->get(),
                'by_status' => $query->clone()
                    ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
                    ->groupBy('status')
                    ->get()
            ]
        ];
    }

    /**
     * Отчет по пользователям
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getUsersReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = User::whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 50);

        // Статистика активности
        $activityStats = [
            'with_bookings' => User::has('bookings')->count(),
            'with_payments' => User::has('payments')->count(),
            'with_reviews' => User::has('reviews')->count(),
        ];

        return [
            'users' => $users,
            'summary' => [
                'total' => $users->total(),
                'new_today' => User::whereDate('created_at', today())->count(),
                'by_role' => $query->clone()
                    ->selectRaw('role, COUNT(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role'),
                'activity' => $activityStats
            ]
        ];
    }

    /**
     * Отчет по номерам
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getRoomsReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = Room::with(['hotel', 'bookings' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('check_in', [$startDate, $endDate])
                ->orWhereBetween('check_out', [$startDate, $endDate]);
        }]);

        if (!empty($filters['hotel_id'])) {
            $query->where('hotel_id', $filters['hotel_id']);
        }

        if (!empty($filters['room_type'])) {
            $query->where('type', $filters['room_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rooms = $query->orderBy('price_per_night', 'asc')
            ->paginate($filters['per_page'] ?? 50);

        // Расчет загрузки номеров
        $rooms->getCollection()->transform(function ($room) use ($startDate, $endDate) {
            $daysInPeriod = $startDate->diffInDays($endDate) + 1;
            $bookedDays = $room->bookings->sum(function ($booking) use ($startDate, $endDate) {
                $bookingStart = max($booking->check_in, $startDate);
                $bookingEnd = min($booking->check_out, $endDate);
                return $bookingStart->diffInDays($bookingEnd) + 1;
            });

            $room->occupancy_rate = $daysInPeriod > 0
                ? round(($bookedDays / $daysInPeriod) * 100, 2)
                : 0;

            $room->revenue = $room->bookings->sum('total_price');

            return $room;
        });

        return [
            'rooms' => $rooms,
            'summary' => [
                'total' => $rooms->total(),
                'average_price' => $rooms->avg('price_per_night'),
                'total_revenue' => $rooms->sum('revenue'),
                'average_occupancy' => $rooms->avg('occupancy_rate'),
                'by_type' => $query->clone()
                    ->selectRaw('type, COUNT(*) as count, AVG(price_per_night) as avg_price')
                    ->groupBy('type')
                    ->get()
            ]
        ];
    }

    /**
     * Финансовый отчет
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getFinancialReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        // Доходы
        $revenueQuery = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$startDate, $endDate]);

        if (!empty($filters['hotel_id'])) {
            $revenueQuery->whereHas('booking', function ($q) use ($filters) {
                $q->where('hotel_id', $filters['hotel_id']);
            });
        }

        $revenues = $revenueQuery->get();

        // Группировка по дням/месяцам
        $revenueByPeriod = $revenues->groupBy(function ($payment) use ($filters) {
            return $payment->payment_date->format($filters['group_by'] ?? 'Y-m');
        })->map(function ($group) {
            return $group->sum('amount');
        });

        // Статистика по методам оплаты
        $revenueByMethod = $revenues->groupBy('method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('amount')
            ];
        });

        // Возвраты (refunds)
        $refunds = Payment::where('status', 'refunded')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->sum('amount');

        return [
            'revenues' => $revenues,
            'revenue_by_period' => $revenueByPeriod,
            'revenue_by_method' => $revenueByMethod,
            'summary' => [
                'total_revenue' => $revenues->sum('amount'),
                'total_refunds' => $refunds,
                'net_revenue' => $revenues->sum('amount') - $refunds,
                'average_transaction' => $revenues->avg('amount'),
                'transactions_count' => $revenues->count()
            ]
        ];
    }

    /**
     * Отчет по загрузке номеров
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getOccupancyReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        return $this->reportService->generateOccupancyReport($startDate, $endDate, $filters);
    }

    /**
     * Отчет по выручке
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $filters
     * @return array
     */
    private function getRevenueReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        return $this->reportService->generateRevenueReport($startDate, $endDate, $filters);
    }

    /**
     * Расчет итоговых значений
     *
     * @param array $data
     * @param string $reportType
     * @return array
     */
    private function calculateTotals(array $data, string $reportType): array
    {
        switch ($reportType) {
            case 'bookings':
                return [
                    'total_bookings' => $data['summary']['total'] ?? 0,
                    'total_amount' => $data['summary']['total_amount'] ?? 0,
                    'average_amount' => $data['summary']['average_amount'] ?? 0,
                ];
            case 'payments':
                return [
                    'total_payments' => $data['summary']['total'] ?? 0,
                    'total_amount' => $data['summary']['total_amount'] ?? 0,
                    'average_amount' => $data['summary']['average_amount'] ?? 0,
                ];
            case 'financial':
                return $data['summary'] ?? [];
            default:
                return [];
        }
    }

    /**
     * Генерация имени файла для экспорта
     *
     * @param string $reportType
     * @param string $format
     * @param array $filters
     * @return string
     */
    private function generateFilename(string $reportType, string $format, array $filters): string
    {
        $reportNames = [
            'bookings' => 'bookings',
            'payments' => 'payments',
            'users' => 'users',
            'rooms' => 'rooms',
            'financial' => 'financial-report',
            'occupancy' => 'occupancy-report',
            'revenue' => 'revenue-report',
        ];

        $name = $reportNames[$reportType] ?? 'report';
        $dateRange = '';

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date'])->format('Y-m-d');
            $end = Carbon::parse($filters['end_date'])->format('Y-m-d');
            $dateRange = "_{$start}_to_{$end}";
        }

        $extensions = [
            'excel' => 'xlsx',
            'csv' => 'csv',
            'pdf' => 'pdf'
        ];

        $extension = $extensions[$format] ?? 'xlsx';

        return "{$name}{$dateRange}_" . date('Y-m-d_H-i') . ".{$extension}";
    }

    /**
     * Сохранение настроек отчета
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'report_type' => 'required|string',
            'filters' => 'required|array',
            'is_default' => 'boolean'
        ]);

        // Сохраняем настройки отчета в базе данных
        $reportSettings = \App\Models\ReportSetting::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'name' => $validated['name'],
                'report_type' => $validated['report_type']
            ],
            [
                'filters' => json_encode($validated['filters']),
                'is_default' => $validated['is_default'] ?? false
            ]
        );

        return response()->json([
            'message' => 'Настройки отчета сохранены',
            'settings' => $reportSettings
        ]);
    }

    /**
     * Загрузка сохраненных настроек отчетов
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function loadSettings(Request $request): JsonResponse
    {
        $settings = \App\Models\ReportSetting::where('user_id', auth()->id())
            ->orderBy('is_default', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($settings);
    }

    /**
     * Статистика отчетов (сколько раз какие отчеты генерировались)
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = \App\Models\ReportLog::selectRaw('report_type, COUNT(*) as count, MAX(created_at) as last_generated')
            ->where('user_id', auth()->id())
            ->groupBy('report_type')
            ->get();

        return response()->json($stats);
    }
}
