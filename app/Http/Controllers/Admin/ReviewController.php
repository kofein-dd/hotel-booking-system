<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Booking;
use App\Models\User;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-reviews')) {
            abort(403);
        }

        $query = Review::with(['user', 'booking.room']);

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('room_id')) {
            $query->whereHas('booking.room', function ($q) use ($request) {
                $q->where('id', $request->room_id);
            });
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking.room', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Сортировка
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        if (in_array($sortField, ['rating', 'created_at', 'updated_at', 'helpful_count'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $reviews = $query->paginate(30);

        $users = User::where('role', 'user')->get(['id', 'name', 'email']);
        $rooms = Room::where('status', 'active')->get(['id', 'name']);
        $statuses = ['pending', 'approved', 'rejected', 'spam'];
        $ratings = [1, 2, 3, 4, 5];

        // Статистика для отображения
        $stats = [
            'total' => Review::count(),
            'pending' => Review::where('status', 'pending')->count(),
            'approved' => Review::where('status', 'approved')->count(),
            'average_rating' => Review::where('status', 'approved')->avg('rating') ?? 0,
        ];

        return view('admin.reviews.index', compact(
            'reviews',
            'users',
            'rooms',
            'statuses',
            'ratings',
            'stats'
        ));
    }

    /**
     * Display the specified review.
     */
    public function show(Review $review): View
    {
        if (!Gate::allows('view-review', $review)) {
            abort(403);
        }

        $review->load(['user', 'booking.room', 'replies.user', 'helpfulUsers']);

        // История изменений статуса
        $statusHistory = $review->status_history ?? [];

        return view('admin.reviews.show', compact('review', 'statusHistory'));
    }

    /**
     * Approve a review.
     */
    public function approve(Review $review): RedirectResponse
    {
        if (!Gate::allows('approve-reviews')) {
            abort(403);
        }

        if ($review->status === 'approved') {
            return back()->with('warning', 'Отзыв уже одобрен.');
        }

        $review->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->guard('admin')->id(),
            'status_history' => array_merge(
                $review->status_history ?? [],
                [[
                    'status' => 'approved',
                    'timestamp' => now()->toDateTimeString(),
                    'admin_id' => auth()->guard('admin')->id(),
                    'note' => 'Отзыв одобрен администратором',
                ]]
            ),
        ]);

        // Пересчитываем рейтинг номера
        $this->recalculateRoomRating($review->booking->room_id);

        // Уведомляем пользователя
        // Notification::send($review->user, new ReviewApproved($review));

        return back()->with('success', 'Отзыв одобрен и опубликован.');
    }

    /**
     * Reject a review.
     */
    public function reject(Request $request, Review $review): RedirectResponse
    {
        if (!Gate::allows('reject-reviews')) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($review->status === 'rejected') {
            return back()->with('warning', 'Отзыв уже отклонен.');
        }

        $review->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => auth()->guard('admin')->id(),
            'rejection_reason' => $validated['reason'],
            'status_history' => array_merge(
                $review->status_history ?? [],
                [[
                    'status' => 'rejected',
                    'timestamp' => now()->toDateTimeString(),
                    'admin_id' => auth()->guard('admin')->id(),
                    'note' => 'Причина отклонения: ' . $validated['reason'],
                ]]
            ),
        ]);

        // Уведомляем пользователя
        // Notification::send($review->user, new ReviewRejected($review, $validated['reason']));

        return back()->with('success', 'Отзыв отклонен.');
    }

    /**
     * Mark review as spam.
     */
    public function markAsSpam(Review $review): RedirectResponse
    {
        if (!Gate::allows('manage-reviews')) {
            abort(403);
        }

        $review->update([
            'status' => 'spam',
            'status_history' => array_merge(
                $review->status_history ?? [],
                [[
                    'status' => 'spam',
                    'timestamp' => now()->toDateTimeString(),
                    'admin_id' => auth()->guard('admin')->id(),
                    'note' => 'Помечен как спам',
                ]]
            ),
        ]);

        return back()->with('success', 'Отзыв помечен как спам.');
    }

    /**
     * Edit review content.
     */
    public function edit(Review $review): View
    {
        if (!Gate::allows('edit-reviews')) {
            abort(403);
        }

        return view('admin.reviews.edit', compact('review'));
    }

    /**
     * Update review content.
     */
    public function update(Request $request, Review $review): RedirectResponse
    {
        if (!Gate::allows('edit-reviews')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'comment' => 'required|string|min:10|max:2000',
            'rating' => 'required|integer|min:1|max:5',
            'pros' => 'nullable|string|max:500',
            'cons' => 'nullable|string|max:500',
            'is_edited' => 'nullable|boolean',
        ]);

        $validated['is_edited'] = true;
        $validated['edited_at'] = now();
        $validated['edited_by'] = auth()->guard('admin')->id();

        $review->update($validated);

        // Если рейтинг изменился и отзыв одобрен - пересчитываем рейтинг номера
        if ($review->status === 'approved' && $review->getOriginal('rating') != $validated['rating']) {
            $this->recalculateRoomRating($review->booking->room_id);
        }

        return redirect()->route('admin.reviews.show', $review)
            ->with('success', 'Отзыв обновлен.');
    }

    /**
     * Add admin reply to review.
     */
    public function addReply(Request $request, Review $review): RedirectResponse
    {
        if (!Gate::allows('reply-to-reviews')) {
            abort(403);
        }

        $validated = $request->validate([
            'reply' => 'required|string|min:5|max:1000',
            'is_public' => 'nullable|boolean',
        ]);

        $review->replies()->create([
            'user_id' => auth()->guard('admin')->id(),
            'reply' => $validated['reply'],
            'is_admin_reply' => true,
            'is_public' => $validated['is_public'] ?? true,
        ]);

        // Уведомляем пользователя о ответе
        // Notification::send($review->user, new ReviewReplied($review));

        return back()->with('success', 'Ответ добавлен.');
    }

    /**
     * Delete review.
     */
    public function destroy(Review $review): RedirectResponse
    {
        if (!Gate::allows('delete-reviews')) {
            abort(403);
        }

        $roomId = $review->booking->room_id;
        $review->delete();

        // Пересчитываем рейтинг номера после удаления
        $this->recalculateRoomRating($roomId);

        return redirect()->route('admin.reviews.index')
            ->with('success', 'Отзыв удален.');
    }

    /**
     * Bulk actions for reviews.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        if (!Gate::allows('manage-reviews')) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject,spam,delete',
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:reviews,id',
            'reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        $processed = 0;
        $failed = 0;

        foreach ($validated['review_ids'] as $reviewId) {
            try {
                $review = Review::find($reviewId);

                switch ($validated['action']) {
                    case 'approve':
                        if ($review->status !== 'approved') {
                            $review->update([
                                'status' => 'approved',
                                'approved_at' => now(),
                                'approved_by' => auth()->guard('admin')->id(),
                            ]);
                            $this->recalculateRoomRating($review->booking->room_id);
                        }
                        break;

                    case 'reject':
                        if ($review->status !== 'rejected') {
                            $review->update([
                                'status' => 'rejected',
                                'rejected_at' => now(),
                                'rejected_by' => auth()->guard('admin')->id(),
                                'rejection_reason' => $validated['reason'],
                            ]);
                        }
                        break;

                    case 'spam':
                        $review->update(['status' => 'spam']);
                        break;

                    case 'delete':
                        $roomId = $review->booking->room_id;
                        $review->delete();
                        $this->recalculateRoomRating($roomId);
                        break;
                }

                $processed++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error('Bulk action failed for review ' . $reviewId . ': ' . $e->getMessage());
            }
        }

        $message = "Обработано {$processed} отзывов.";
        if ($failed > 0) {
            $message .= " Не удалось обработать {$failed} отзывов.";
        }

        return back()->with('success', $message);
    }

    /**
     * Recalculate room rating.
     */
    private function recalculateRoomRating(int $roomId): void
    {
        $room = Room::find($roomId);
        if (!$room) return;

        $ratingStats = Review::whereHas('booking', function ($query) use ($roomId) {
            $query->where('room_id', $roomId);
        })
            ->where('status', 'approved')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(rating) as average'),
                DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars'),
                DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars'),
                DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars'),
                DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars'),
                DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_stars')
            )
            ->first();

        $room->update([
            'rating' => $ratingStats->average ?? 0,
            'review_count' => $ratingStats->total ?? 0,
            'rating_breakdown' => [
                '5' => $ratingStats->five_stars ?? 0,
                '4' => $ratingStats->four_stars ?? 0,
                '3' => $ratingStats->three_stars ?? 0,
                '2' => $ratingStats->two_stars ?? 0,
                '1' => $ratingStats->one_stars ?? 0,
            ],
        ]);
    }

    /**
     * Get review statistics.
     */
    public function statistics(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Фильтры
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Общая статистика
        $totalStats = Review::select(
            DB::raw('COUNT(*) as total'),
            DB::raw('AVG(rating) as average_rating'),
            DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        // Статистика по рейтингам
        $ratingStats = Review::where('status', 'approved')
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        // Ежедневная статистика
        $dailyStats = Review::where('status', 'approved')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(rating) as avg_rating')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Статистика по номерам
        $roomStats = Room::withCount(['reviews' => function ($query) use ($dateFrom, $dateTo) {
            $query->where('status', 'approved')
                ->whereBetween('reviews.created_at', [$dateFrom, $dateTo . ' 23:59:59']);
        }])
            ->withAvg(['reviews' => function ($query) use ($dateFrom, $dateTo) {
                $query->where('status', 'approved')
                    ->whereBetween('reviews.created_at', [$dateFrom, $dateTo . ' 23:59:59']);
            }], 'rating')
            ->has('reviews')
            ->orderBy('reviews_avg_rating', 'desc')
            ->limit(10)
            ->get();

        // Самые активные рецензенты
        $activeReviewers = User::withCount(['reviews' => function ($query) use ($dateFrom, $dateTo) {
            $query->where('status', 'approved')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
        }])
            ->has('reviews')
            ->orderBy('reviews_count', 'desc')
            ->limit(10)
            ->get();

        // Время ответа на отзывы
        $responseTimeStats = Review::whereNotNull('first_reply_at')
            ->where('status', 'approved')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, first_reply_at)) as avg_hours'))
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        // Слова-индикаторы (часто упоминаемые слова)
        $commonWords = $this->extractCommonWords($dateFrom, $dateTo);

        return view('admin.reviews.statistics', compact(
            'totalStats',
            'ratingStats',
            'dailyStats',
            'roomStats',
            'activeReviewers',
            'responseTimeStats',
            'commonWords',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Extract common words from reviews.
     */
    private function extractCommonWords(string $dateFrom, string $dateTo): array
    {
        // Получаем все одобренные отзывы за период
        $reviews = Review::where('status', 'approved')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->pluck('comment')
            ->implode(' ');

        // Удаляем спецсимволы и приводим к нижнему регистру
        $text = preg_replace('/[^a-zа-яё\s]/ui', ' ', $reviews);
        $text = mb_strtolower($text, 'UTF-8');

        // Разбиваем на слова
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Удаляем стоп-слова
        $stopWords = ['и', 'в', 'на', 'с', 'по', 'для', 'не', 'что', 'это', 'так', 'как', 'у', 'от', 'к', 'до', 'за'];
        $words = array_diff($words, $stopWords);

        // Считаем частоту
        $wordCounts = array_count_values($words);
        arsort($wordCounts);

        // Берем топ-20
        return array_slice($wordCounts, 0, 20, true);
    }

    /**
     * Export reviews.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-reviews')) {
            abort(403);
        }

        $reviews = Review::with(['user', 'booking.room'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('rating'), function ($query) use ($request) {
                $query->where('rating', $request->rating);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->where('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reviews_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($reviews) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM для корректного отображения кириллицы в Excel
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID отзыва',
                'Пользователь',
                'Email',
                'Номер',
                'Рейтинг',
                'Заголовок',
                'Отзыв',
                'Плюсы',
                'Минусы',
                'Статус',
                'Полезно',
                'Создан',
                'Одобрен',
                'Ответ администратора',
            ], ';');

            foreach ($reviews as $review) {
                $adminReply = $review->replies()
                    ->where('is_admin_reply', true)
                    ->first();

                fputcsv($file, [
                    $review->id,
                    $review->user->name ?? 'N/A',
                    $review->user->email ?? 'N/A',
                    $review->booking->room->name ?? 'N/A',
                    $review->rating,
                    $review->title,
                    $review->comment,
                    $review->pros ?? '',
                    $review->cons ?? '',
                    $this->getStatusName($review->status),
                    $review->helpful_count,
                    $review->created_at->format('d.m.Y H:i'),
                    $review->approved_at ? $review->approved_at->format('d.m.Y H:i') : '',
                    $adminReply ? $adminReply->reply : '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get review status name in Russian.
     */
    private function getStatusName(string $status): string
    {
        return match($status) {
            'pending' => 'Ожидает модерации',
            'approved' => 'Одобрен',
            'rejected' => 'Отклонен',
            'spam' => 'Спам',
            default => $status,
        };
    }

    /**
     * Dashboard widget data.
     */
    public function dashboardWidget(): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('view-dashboard')) {
            abort(403);
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayReviews = Review::whereDate('created_at', $today)->count();
        $yesterdayReviews = Review::whereDate('created_at', $yesterday)->count();
        $pendingReviews = Review::where('status', 'pending')->count();
        $averageRating = Review::where('status', 'approved')->avg('rating') ?? 0;

        return response()->json([
            'today_count' => $todayReviews,
            'yesterday_count' => $yesterdayReviews,
            'growth_percent' => $yesterdayReviews > 0
                ? round((($todayReviews - $yesterdayReviews) / $yesterdayReviews) * 100, 2)
                : 0,
            'pending_count' => $pendingReviews,
            'average_rating' => round($averageRating, 1),
        ]);
    }

    /**
     * Get reviews awaiting moderation.
     */
    public function awaitingModeration(): View
    {
        if (!Gate::allows('manage-reviews')) {
            abort(403);
        }

        $reviews = Review::with(['user', 'booking.room'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('admin.reviews.awaiting-moderation', compact('reviews'));
    }

    /**
     * Update helpful count (for user interaction tracking).
     */
    public function updateHelpful(Request $request, Review $review): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('manage-reviews')) {
            abort(403);
        }

        $validated = $request->validate([
            'helpful' => 'required|boolean',
        ]);

        $change = $validated['helpful'] ? 1 : -1;

        $review->increment('helpful_count', $change);

        return response()->json([
            'success' => true,
            'helpful_count' => $review->helpful_count,
        ]);
    }

    /**
     * Get review analytics for charts.
     */
    public function analytics(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $period = $request->get('period', 'month'); // day, week, month, year
        $limit = match($period) {
            'day' => 24,
            'week' => 7,
            'month' => 30,
            'year' => 12,
            default => 30,
        };

        $data = Review::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('AVG(rating) as avg_rating')
        )
            ->where('created_at', '>=', now()->subDays($limit))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'labels' => $data->pluck('date'),
            'counts' => $data->pluck('count'),
            'ratings' => $data->pluck('avg_rating'),
        ]);
    }

    /**
     * Feature a review (show on homepage).
     */
    public function feature(Review $review): RedirectResponse
    {
        if (!Gate::allows('feature-reviews')) {
            abort(403);
        }

        $review->update([
            'is_featured' => !$review->is_featured,
            'featured_at' => $review->is_featured ? null : now(),
        ]);

        $message = $review->is_featured
            ? 'Отзыв добавлен в избранные'
            : 'Отзыв удален из избранных';

        return back()->with('success', $message);
    }

    /**
     * Get featured reviews.
     */
    public function featured(): View
    {
        if (!Gate::allows('manage-reviews')) {
            abort(403);
        }

        $reviews = Review::with(['user', 'booking.room'])
            ->where('is_featured', true)
            ->where('status', 'approved')
            ->orderBy('featured_at', 'desc')
            ->paginate(20);

        return view('admin.reviews.featured', compact('reviews'));
    }

    /**
     * Import reviews from external sources.
     */
    public function import(): View
    {
        if (!Gate::allows('import-reviews')) {
            abort(403);
        }

        return view('admin.reviews.import');
    }

    /**
     * Process imported reviews.
     */
    public function processImport(Request $request): RedirectResponse
    {
        if (!Gate::allows('import-reviews')) {
            abort(403);
        }

        $validated = $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:2048',
            'source' => 'required|string|max:100',
        ]);

        try {
            $file = $request->file('import_file');
            $path = $file->store('temp');
            $fullPath = storage_path('app/' . $path);

            $imported = 0;
            $failed = 0;

            if (($handle = fopen($fullPath, 'r')) !== false) {
                // Пропускаем заголовок
                fgetcsv($handle);

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    try {
                        // Парсим данные CSV
                        // Формат: user_email,room_name,rating,title,comment,pros,cons,date

                        $user = User::where('email', $data[0])->first();
                        $room = Room::where('name', $data[1])->first();

                        if ($user && $room) {
                            // Создаем фиктивное бронирование для отзыва
                            $booking = Booking::create([
                                'user_id' => $user->id,
                                'room_id' => $room->id,
                                'check_in' => now()->subDays(30),
                                'check_out' => now()->subDays(27),
                                'guests_count' => 2,
                                'total_price' => 10000,
                                'status' => 'completed',
                                'payment_status' => 'paid',
                                'is_imported' => true,
                            ]);

                            Review::create([
                                'user_id' => $user->id,
                                'booking_id' => $booking->id,
                                'rating' => (int)$data[2],
                                'title' => $data[3],
                                'comment' => $data[4],
                                'pros' => $data[5] ?? null,
                                'cons' => $data[6] ?? null,
                                'status' => 'approved',
                                'approved_at' => now(),
                                'approved_by' => auth()->guard('admin')->id(),
                                'created_at' => $data[7] ?? now(),
                                'source' => $validated['source'],
                                'is_imported' => true,
                            ]);

                            $imported++;
                        } else {
                            $failed++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        \Log::error('Import review failed: ' . $e->getMessage());
                    }
                }
                fclose($handle);
            }

            // Удаляем временный файл
            unlink($fullPath);

            $message = "Импортировано {$imported} отзывов.";
            if ($failed > 0) {
                $message .= " Не удалось импортировать {$failed} отзывов.";
            }

            return redirect()->route('admin.reviews.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка импорта: ' . $e->getMessage()]);
        }
    }
}
