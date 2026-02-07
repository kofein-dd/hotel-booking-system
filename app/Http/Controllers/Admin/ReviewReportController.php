<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewReport;
use App\Models\Review;
use App\Models\User;
use App\Http\Requests\ReviewReport\StoreReviewReportRequest;
use App\Http\Requests\ReviewReport\UpdateReviewReportRequest;
use App\Http\Resources\ReviewReportResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReviewReportController extends Controller
{
    /**
     * Display a listing of the review reports.
     */
    public function index(Request $request)
    {
        try {
            $query = ReviewReport::with(['review', 'user', 'resolvedBy'])
                ->orderBy('status', 'asc')
                ->orderBy('created_at', 'desc');

            // Фильтр по статусу
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Фильтр по типу жалобы
            if ($request->has('report_type') && $request->report_type) {
                $query->where('report_type', $request->report_type);
            }

            // Поиск по пользователю (кто пожаловался)
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Поиск по отзыву
            if ($request->has('review_id') && $request->review_id) {
                $query->where('review_id', $request->review_id);
            }

            // Поиск по тексту жалобы
            if ($request->has('description') && $request->description) {
                $query->where('description', 'LIKE', '%' . $request->description . '%');
            }

            // Поиск по дате
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            // Статистика
            $stats = [
                'total_reports' => ReviewReport::count(),
                'pending_reports' => ReviewReport::where('status', 'pending')->count(),
                'resolved_reports' => ReviewReport::where('status', 'resolved')->count(),
                'rejected_reports' => ReviewReport::where('status', 'rejected')->count(),
                'today_reports' => ReviewReport::whereDate('created_at', today())->count(),
            ];

            // Типы жалоб (статистика)
            $reportTypes = ReviewReport::select('report_type', DB::raw('COUNT(*) as count'))
                ->groupBy('report_type')
                ->orderBy('count', 'desc')
                ->get();

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $reports = $query->paginate($perPage);

            // Для API
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => ReviewReportResource::collection($reports),
                    'stats' => $stats,
                    'report_types' => $reportTypes,
                    'meta' => [
                        'total' => $reports->total(),
                        'per_page' => $reports->perPage(),
                        'current_page' => $reports->currentPage(),
                        'last_page' => $reports->lastPage(),
                    ]
                ]);
            }

            // Для веб-интерфейса
            return view('admin.review-reports.index', compact('reports', 'stats', 'reportTypes'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении жалоб на отзывы',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()->with('error', 'Ошибка при получении жалоб на отзывы: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified review report.
     */
    public function show(Request $request, $id)
    {
        try {
            $report = ReviewReport::with(['review', 'user', 'resolvedBy'])
                ->findOrFail($id);

            // Получаем все жалобы на этот отзыв
            $allReportsForReview = ReviewReport::where('review_id', $report->review_id)
                ->where('id', '!=', $report->id)
                ->with('user')
                ->get();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => new ReviewReportResource($report),
                    'related_reports' => ReviewReportResource::collection($allReportsForReview)
                ]);
            }

            return view('admin.review-reports.show', compact('report', 'allReportsForReview'));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Жалоба на отзыв не найдена'
                ], 404);
            }

            return redirect()->route('admin.review-reports.index')
                ->with('error', 'Жалоба на отзыв не найдена');
        }
    }

    /**
     * Mark report as resolved and take action on review.
     */
    public function resolve(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $report = ReviewReport::with(['review', 'review.user'])->findOrFail($id);
            $review = $report->review;

            // Проверяем, что жалоба еще не обработана
            if ($report->status !== 'pending') {
                throw new \Exception('Жалоба уже обработана');
            }

            $action = $request->action; // 'delete_review', 'hide_review', 'warn_user', 'no_action'
            $resolutionNotes = $request->resolution_notes;

            // Выполняем действие в зависимости от выбора
            switch ($action) {
                case 'delete_review':
                    // Удаляем отзыв
                    $review->delete();
                    $actionDescription = 'Отзыв удален';
                    break;

                case 'hide_review':
                    // Скрываем отзыв
                    $review->update(['is_hidden' => true]);
                    $actionDescription = 'Отзыв скрыт';
                    break;

                case 'warn_user':
                    // Отправляем предупреждение пользователю
                    $this->sendWarningToUser($review->user, $report, $resolutionNotes);
                    $actionDescription = 'Пользователю отправлено предупреждение';
                    break;

                case 'no_action':
                    // Никаких действий не требуется
                    $actionDescription = 'Жалоба отклонена, отзыв соответствует правилам';
                    break;

                default:
                    throw new \Exception('Неверное действие');
            }

            // Обновляем статус жалобы
            $report->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolution_action' => $action,
                'resolution_notes' => $resolutionNotes,
                'action_description' => $actionDescription
            ]);

            // Логируем действие
            $this->logResolutionAction($report, $action, $resolutionNotes);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Жалоба успешно обработана',
                    'data' => new ReviewReportResource($report),
                    'action_taken' => $actionDescription
                ]);
            }

            return redirect()->route('admin.review-reports.index')
                ->with('success', 'Жалоба успешно обработана');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при обработке жалобы',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ошибка при обработке жалобы: ' . $e->getMessage());
        }
    }

    /**
     * Reject the report (mark as invalid).
     */
    public function reject(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $report = ReviewReport::findOrFail($id);

            if ($report->status !== 'pending') {
                throw new \Exception('Жалоба уже обработана');
            }

            $report->update([
                'status' => 'rejected',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolution_action' => 'rejected',
                'resolution_notes' => $request->get('rejection_notes', 'Жалоба отклонена как необоснованная'),
                'action_description' => 'Жалоба отклонена'
            ]);

            // Логируем отклонение
            $this->logResolutionAction($report, 'rejected', $request->get('rejection_notes'));

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Жалоба отклонена',
                    'data' => new ReviewReportResource($report)
                ]);
            }

            return redirect()->route('admin.review-reports.index')
                ->with('success', 'Жалоба отклонена');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при отклонении жалобы',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при отклонении жалобы: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified review report.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $report = ReviewReport::findOrFail($id);
            $report->delete();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Жалоба удалена'
                ]);
            }

            return redirect()->route('admin.review-reports.index')
                ->with('success', 'Жалоба удалена');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при удалении жалобы',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при удалении жалобы: ' . $e->getMessage());
        }
    }

    /**
     * Bulk resolve reports.
     */
    public function bulkResolve(Request $request)
    {
        try {
            DB::beginTransaction();

            $reportIds = $request->report_ids;
            $action = $request->action;
            $resolutionNotes = $request->resolution_notes;

            if (empty($reportIds)) {
                throw new \Exception('Не выбраны жалобы для обработки');
            }

            $reports = ReviewReport::whereIn('id', $reportIds)
                ->where('status', 'pending')
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('Не найдено необработанных жалоб среди выбранных');
            }

            $processedCount = 0;
            $skippedCount = 0;

            foreach ($reports as $report) {
                try {
                    $report->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                        'resolution_action' => $action,
                        'resolution_notes' => $resolutionNotes,
                        'action_description' => 'Обработано массово'
                    ]);

                    $processedCount++;
                } catch (\Exception $e) {
                    $skippedCount++;
                    continue;
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Обработано $processedCount жалоб, пропущено $skippedCount",
                    'processed' => $processedCount,
                    'skipped' => $skippedCount
                ]);
            }

            return redirect()->route('admin.review-reports.index')
                ->with('success', "Обработано $processedCount жалоб, пропущено $skippedCount");

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при массовой обработке жалоб',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при массовой обработке жалоб: ' . $e->getMessage());
        }
    }

    /**
     * Get review report statistics.
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

            // Жалобы по дням
            $reportsByDay = ReviewReport::select(
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

            // Типы жалоб
            $reportsByType = ReviewReport::select(
                'report_type',
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('report_type')
                ->orderBy('count', 'desc')
                ->get();

            // Статусы жалоб
            $reportsByStatus = ReviewReport::select(
                'status',
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('status')
                ->orderBy('count', 'desc')
                ->get();

            // Самые активные пользователи по жалобам
            $activeReporters = ReviewReport::select(
                'user_id',
                DB::raw('COUNT(*) as count')
            )
                ->with('user')
                ->where('created_at', '>=', $startDate)
                ->groupBy('user_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Отзывы с наибольшим количеством жалоб
            $mostReportedReviews = ReviewReport::select(
                'review_id',
                DB::raw('COUNT(*) as report_count')
            )
                ->with('review')
                ->where('created_at', '>=', $startDate)
                ->groupBy('review_id')
                ->orderBy('report_count', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'reports_by_day' => $reportsByDay,
                    'reports_by_type' => $reportsByType,
                    'reports_by_status' => $reportsByStatus,
                    'active_reporters' => $activeReporters,
                    'most_reported_reviews' => $mostReportedReviews,
                    'period' => $period,
                    'total_reports_period' => ReviewReport::where('created_at', '>=', $startDate)->count()
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
     * Export review reports.
     */
    public function export(Request $request)
    {
        try {
            $query = ReviewReport::with(['review', 'user', 'resolvedBy'])
                ->orderBy('created_at', 'desc');

            // Применение фильтров
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $reports = $query->get();

            $format = $request->get('format', 'csv');

            if ($format === 'excel') {
                return $this->exportToExcel($reports);
            }

            return $this->exportToCsv($reports);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка при экспорте: ' . $e->getMessage());
        }
    }

    /**
     * Send warning to user about their review.
     */
    public function sendWarning(Request $request, $id)
    {
        try {
            $report = ReviewReport::with(['review', 'review.user'])->findOrFail($id);
            $user = $report->review->user;

            $warningMessage = $request->get('warning_message', 'Ваш отзыв был признан нарушающим правила сообщества.');

            // Отправляем уведомление пользователю
            $this->sendWarningNotification($user, $report, $warningMessage);

            // Обновляем жалобу
            $report->update([
                'warning_sent_at' => now(),
                'warning_sent_by' => auth()->id(),
                'warning_message' => $warningMessage
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Предупреждение отправлено пользователю',
                    'data' => new ReviewReportResource($report)
                ]);
            }

            return redirect()->back()
                ->with('success', 'Предупреждение отправлено пользователю');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при отправке предупреждения',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при отправке предупреждения: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to export to CSV.
     */
    private function exportToCsv($reports)
    {
        $fileName = 'review-reports-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function() use ($reports) {
            $file = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($file, [
                'ID',
                'ID отзыва',
                'Текст отзыва',
                'Пользователь (жалобщик)',
                'Тип жалобы',
                'Описание жалобы',
                'Статус',
                'Дата создания',
                'Дата разрешения',
                'Кто разрешил',
                'Действие',
                'Примечания',
                'IP адрес'
            ]);

            // Данные
            foreach ($reports as $report) {
                fputcsv($file, [
                    $report->id,
                    $report->review_id,
                    $report->review ? substr($report->review->comment, 0, 100) . '...' : '-',
                    $report->user ? $report->user->email : '-',
                    $report->report_type,
                    $report->description,
                    $this->getStatusLabel($report->status),
                    $report->created_at->format('Y-m-d H:i:s'),
                    $report->resolved_at ? $report->resolved_at->format('Y-m-d H:i:s') : '-',
                    $report->resolvedBy ? $report->resolvedBy->email : '-',
                    $report->action_description ?: '-',
                    $report->resolution_notes ?: '-',
                    $report->ip_address
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Send warning notification to user.
     */
    private function sendWarningToUser($user, $report, $notes)
    {
        // Отправляем email уведомление
        \Illuminate\Support\Facades\Mail::to($user->email)->send(
            new \App\Mail\ReviewWarningMail($user, $report, $notes)
        );

        // Создаем уведомление в системе
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'review_warning',
            'title' => 'Предупреждение о нарушении правил',
            'message' => 'Ваш отзыв был признан нарушающим правила сообщества. ' . $notes,
            'data' => [
                'review_id' => $report->review_id,
                'report_id' => $report->id
            ]
        ]);
    }

    /**
     * Log resolution action.
     */
    private function logResolutionAction($report, $action, $notes)
    {
        \App\Models\AuditLog::create([
            'user_id' => $report->user_id,
            'admin_id' => auth()->id(),
            'action_type' => 'review_report_resolution',
            'model_type' => ReviewReport::class,
            'model_id' => $report->id,
            'description' => "Обработана жалоба #{$report->id} на отзыв #{$report->review_id}. Действие: $action",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }

    /**
     * Get status label.
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'В ожидании',
            'resolved' => 'Обработано',
            'rejected' => 'Отклонено',
        ];

        return $labels[$status] ?? $status;
    }
}
