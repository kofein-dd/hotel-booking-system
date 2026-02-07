<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\ReviewRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * Показать все отзывы с пагинацией
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'room', 'hotel'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc');

        // Фильтр по отелю
        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->input('hotel_id'));
        }

        // Фильтр по номеру
        if ($request->has('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        // Фильтр по рейтингу
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->input('min_rating'));
        }

        // Фильтр по типу (отель/номер)
        if ($request->has('type')) {
            $query->where('reviewable_type', $request->input('type'));
        }

        $perPage = $request->input('per_page', 10);
        $reviews = $query->paginate($perPage);

        // Статистика рейтингов
        $ratingStats = Review::where('status', 'approved')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'reviews' => $reviews,
                'rating_stats' => $ratingStats,
                'average_rating' => Review::where('status', 'approved')->avg('rating')
            ]);
        }

        return view('frontend.reviews.index', compact('reviews', 'ratingStats'));
    }

    /**
     * Показать форму создания отзыва
     *
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function create(Request $request)
    {
        $bookingId = $request->input('booking_id');
        $booking = null;

        if ($bookingId) {
            $booking = Booking::where('id', $bookingId)
                ->where('user_id', Auth::id())
                ->whereIn('status', ['completed', 'checked_out'])
                ->with(['room', 'hotel'])
                ->first();

            if (!$booking) {
                return redirect()
                    ->route('frontend.bookings.index')
                    ->with('error', 'Бронирование не найдено или недоступно для отзыва');
            }

            // Проверяем, не оставлял ли уже пользователь отзыв на это бронирование
            $existingReview = Review::where('booking_id', $booking->id)->first();
            if ($existingReview) {
                return redirect()
                    ->route('frontend.reviews.edit', $existingReview)
                    ->with('info', 'Вы уже оставили отзыв на это бронирование');
            }
        }

        // Получаем список завершенных бронирований пользователя для выбора
        $completedBookings = Booking::where('user_id', Auth::id())
            ->whereIn('status', ['completed', 'checked_out'])
            ->with(['room', 'hotel'])
            ->whereDoesntHave('review')
            ->orderBy('check_out', 'desc')
            ->limit(10)
            ->get();

        return view('frontend.reviews.create', compact('booking', 'completedBookings'));
    }

    /**
     * Сохранить новый отзыв
     *
     * @param ReviewRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(ReviewRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        // Определяем, нужна ли модерация отзывов
        $needsModeration = setting('reviews.require_moderation', true);
        $data['status'] = $needsModeration ? 'pending' : 'approved';

        // Если отзыв привязан к бронированию, проверяем доступ
        if (!empty($data['booking_id'])) {
            $booking = Booking::where('id', $data['booking_id'])
                ->where('user_id', Auth::id())
                ->first();

            if (!$booking) {
                return response()->json([
                    'error' => 'Бронирование не найдено или недоступно'
                ], 403);
            }

            // Проверяем, не оставлял ли уже отзыв на это бронирование
            $existingReview = Review::where('booking_id', $booking->id)->first();
            if ($existingReview) {
                return response()->json([
                    'error' => 'Вы уже оставили отзыв на это бронирование'
                ], 409);
            }

            // Автоматически заполняем отель/номер из бронирования
            if (empty($data['hotel_id'])) {
                $data['hotel_id'] = $booking->hotel_id;
            }
            if (empty($data['room_id']) && $booking->room_id) {
                $data['room_id'] = $booking->room_id;
                $data['reviewable_type'] = 'room';
                $data['reviewable_id'] = $booking->room_id;
            } else {
                $data['reviewable_type'] = 'hotel';
                $data['reviewable_id'] = $booking->hotel_id;
            }
        }

        // Если есть загруженные фотографии
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('reviews/photos', 'public');
                $photoPaths[] = $path;
            }
            $data['photos'] = json_encode($photoPaths);
        }

        $review = Review::create($data);

        // Обновляем средний рейтинг для отеля/номера
        $this->updateAverageRating($review);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $needsModeration
                    ? 'Отзыв отправлен на модерацию. Спасибо!'
                    : 'Отзыв успешно опубликован!',
                'review' => $review
            ], 201);
        }

        return redirect()
            ->route('frontend.reviews.show', $review)
            ->with('success', $needsModeration
                ? 'Отзыв отправлен на модерацию. Спасибо!'
                : 'Отзыв успешно опубликован!');
    }

    /**
     * Показать детали отзыва
     *
     * @param Review $review
     * @return View|JsonResponse
     */
    public function show(Review $review)
    {
        // Проверяем доступ (отзыв должен быть approved или принадлежать пользователю)
        if ($review->status !== 'approved' && $review->user_id !== Auth::id()) {
            abort(404, 'Отзыв не найден');
        }

        $review->load(['user', 'room', 'hotel', 'booking']);

        // Похожие отзывы
        $similarReviews = Review::where('status', 'approved')
            ->where('id', '!=', $review->id)
            ->where(function($query) use ($review) {
                $query->where('hotel_id', $review->hotel_id)
                    ->orWhere('room_id', $review->room_id);
            })
            ->with(['user', 'room'])
            ->limit(5)
            ->get();

        if (request()->wantsJson()) {
            return response()->json($review);
        }

        return view('frontend.reviews.show', compact('review', 'similarReviews'));
    }

    /**
     * Показать форму редактирования отзыва
     *
     * @param Review $review
     * @return View|RedirectResponse
     */
    public function edit(Review $review)
    {
        if ($review->user_id !== Auth::id()) {
            abort(403, 'Вы не можете редактировать этот отзыв');
        }

        if ($review->status === 'approved' && setting('reviews.allow_edit_approved', false) === false) {
            return redirect()
                ->route('frontend.reviews.show', $review)
                ->with('error', 'Опубликованные отзывы нельзя редактировать');
        }

        $review->load(['booking.room', 'booking.hotel']);

        return view('frontend.reviews.edit', compact('review'));
    }

    /**
     * Обновить отзыв
     *
     * @param ReviewRequest $request
     * @param Review $review
     * @return RedirectResponse|JsonResponse
     */
    public function update(ReviewRequest $request, Review $review)
    {
        if ($review->user_id !== Auth::id()) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        if ($review->status === 'approved' && setting('reviews.allow_edit_approved', false) === false) {
            return response()->json([
                'error' => 'Опубликованные отзывы нельзя редактировать'
            ], 403);
        }

        $data = $request->validated();

        // Если изменен рейтинг, нужно пересчитать средний
        $ratingChanged = $review->rating != $data['rating'];

        // Обновляем фотографии, если есть новые
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('reviews/photos', 'public');
                $photoPaths[] = $path;
            }

            // Сохраняем старые фото, если не указано их удаление
            if ($request->input('keep_old_photos', true) && !empty($review->photos)) {
                $oldPhotos = json_decode($review->photos, true);
                $photoPaths = array_merge($oldPhotos, $photoPaths);
            }

            $data['photos'] = json_encode($photoPaths);
        }

        // Если пользователь удалил некоторые фото
        if ($request->has('delete_photos')) {
            $photosToDelete = $request->input('delete_photos', []);
            if (!empty($review->photos)) {
                $currentPhotos = json_decode($review->photos, true);
                $updatedPhotos = array_diff($currentPhotos, $photosToDelete);

                // Удаляем файлы с сервера
                foreach ($photosToDelete as $photoPath) {
                    if (\Storage::disk('public')->exists($photoPath)) {
                        \Storage::disk('public')->delete($photoPath);
                    }
                }

                $data['photos'] = json_encode(array_values($updatedPhotos));
            }
        }

        // Если отзыв был отклонен, при редактировании ставим статус pending
        if ($review->status === 'rejected') {
            $data['status'] = 'pending';
        }

        $review->update($data);

        // Обновляем средний рейтинг, если изменился рейтинг
        if ($ratingChanged) {
            $this->updateAverageRating($review);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Отзыв успешно обновлен',
                'review' => $review
            ]);
        }

        return redirect()
            ->route('frontend.reviews.show', $review)
            ->with('success', 'Отзыв успешно обновлен');
    }

    /**
     * Удалить отзыв
     *
     * @param Review $review
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(Review $review, Request $request)
    {
        if ($review->user_id !== Auth::id()) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        // Удаляем прикрепленные фото
        if (!empty($review->photos)) {
            $photos = json_decode($review->photos, true);
            foreach ($photos as $photoPath) {
                if (\Storage::disk('public')->exists($photoPath)) {
                    \Storage::disk('public')->delete($photoPath);
                }
            }
        }

        // Сохраняем информацию для обновления рейтинга
        $reviewableType = $review->reviewable_type;
        $reviewableId = $review->reviewable_id;

        $review->delete();

        // Обновляем средний рейтинг
        if ($reviewableType && $reviewableId) {
            $this->recalculateAverageRating($reviewableType, $reviewableId);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Отзыв успешно удален'
            ]);
        }

        return redirect()
            ->route('frontend.reviews.index')
            ->with('success', 'Отзыв успешно удален');
    }

    /**
     * Отзывы текущего пользователя
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function myReviews(Request $request)
    {
        $reviews = Review::where('user_id', Auth::id())
            ->with(['room', 'hotel', 'booking'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($reviews);
        }

        return view('frontend.reviews.my-reviews', compact('reviews'));
    }

    /**
     * Полезность отзыва (лайк/дизлайк)
     *
     * @param Review $review
     * @param Request $request
     * @return JsonResponse
     */
    public function helpful(Review $review, Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:like,dislike'
        ]);

        $userId = Auth::id();
        $action = $request->input('action');

        // Проверяем, не оценивал ли уже пользователь этот отзыв
        $existingVote = $review->helpfulVotes()
            ->where('user_id', $userId)
            ->first();

        if ($existingVote) {
            if ($existingVote->type === $action) {
                // Удаляем оценку, если пользователь нажал на ту же кнопку
                $existingVote->delete();
                $message = 'Ваша оценка удалена';
            } else {
                // Меняем тип оценки
                $existingVote->update(['type' => $action]);
                $message = 'Ваша оценка изменена';
            }
        } else {
            // Создаем новую оценку
            $review->helpfulVotes()->create([
                'user_id' => $userId,
                'type' => $action
            ]);
            $message = 'Спасибо за вашу оценку!';
        }

        // Обновляем счетчики
        $review->refresh();

        return response()->json([
            'message' => $message,
            'likes' => $review->likes_count,
            'dislikes' => $review->dislikes_count,
            'user_vote' => $review->userVote ? $review->userVote->type : null
        ]);
    }

    /**
     * Обновить средний рейтинг для отеля/номера
     *
     * @param Review $review
     * @return void
     */
    private function updateAverageRating(Review $review): void
    {
        if ($review->reviewable_type === 'hotel' && $review->hotel_id) {
            $hotel = Hotel::find($review->hotel_id);
            if ($hotel) {
                $averageRating = Review::where('hotel_id', $hotel->id)
                    ->where('status', 'approved')
                    ->avg('rating');
                $hotel->update(['average_rating' => round($averageRating, 1)]);
            }
        } elseif ($review->reviewable_type === 'room' && $review->room_id) {
            $room = Room::find($review->room_id);
            if ($room) {
                $averageRating = Review::where('room_id', $room->id)
                    ->where('status', 'approved')
                    ->avg('rating');
                $room->update(['average_rating' => round($averageRating, 1)]);
            }
        }
    }

    /**
     * Пересчитать средний рейтинг
     *
     * @param string $type
     * @param int $id
     * @return void
     */
    private function recalculateAverageRating(string $type, int $id): void
    {
        if ($type === 'hotel') {
            $hotel = Hotel::find($id);
            if ($hotel) {
                $averageRating = Review::where('hotel_id', $hotel->id)
                    ->where('status', 'approved')
                    ->avg('rating');
                $hotel->update(['average_rating' => round($averageRating ?? 0, 1)]);
            }
        } elseif ($type === 'room') {
            $room = Room::find($id);
            if ($room) {
                $averageRating = Review::where('room_id', $room->id)
                    ->where('status', 'approved')
                    ->avg('rating');
                $room->update(['average_rating' => round($averageRating ?? 0, 1)]);
            }
        }
    }
}
