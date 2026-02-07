<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Notification;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

class ProfileController extends Controller
{
    /**
     * Middleware для проверки аутентификации.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('verified')->except(['index', 'bookings', 'payments', 'reviews']);
    }

    /**
     * Display user profile dashboard.
     */
    public function index(): View
    {
        $user = Auth::user();
        $user->loadCount(['bookings', 'reviews', 'unreadNotifications']);

        // Последние бронирования
        $recentBookings = $user->bookings()
            ->with(['room', 'payment'])
            ->latest()
            ->limit(5)
            ->get();

        // Последние платежи
        $recentPayments = $user->payments()
            ->with('booking')
            ->latest()
            ->limit(5)
            ->get();

        // Последние отзывы
        $recentReviews = $user->reviews()
            ->with('booking.room')
            ->latest()
            ->limit(5)
            ->get();

        // Непрочитанные уведомления
        $unreadNotifications = $user->notifications()
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Статистика пользователя
        $stats = [
            'total_bookings' => $user->bookings()->count(),
            'active_bookings' => $user->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>=', now())
                ->count(),
            'total_spent' => $user->payments()
                ->where('status', 'completed')
                ->sum('amount'),
            'reviews_count' => $user->reviews()->count(),
            'average_rating' => $user->reviews()
                    ->where('status', 'approved')
                    ->avg('rating') ?? 0,
        ];

        // Предстоящие заезды
        $upcomingCheckIns = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '>=', now())
            ->with('room')
            ->orderBy('check_in')
            ->limit(3)
            ->get();

        return view('profile.index', compact(
            'user',
            'recentBookings',
            'recentPayments',
            'recentReviews',
            'unreadNotifications',
            'stats',
            'upcomingCheckIns'
        ));
    }

    /**
     * Show profile edit form.
     */
    public function edit(): View
    {
        $user = Auth::user();

        return view('profile.edit', compact('user'));
    }

    /**
     * Update user profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user->id),
            ],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferences' => 'nullable|array',
            'notification_preferences' => 'nullable|array',
            'marketing_consent' => 'nullable|boolean',
            'telegram_chat_id' => 'nullable|string|max:100',
        ]);

        // Если меняем email, сбрасываем подтверждение
        if ($user->email !== $validated['email']) {
            $validated['email_verified_at'] = null;
            $user->sendEmailVerificationNotification();
        }

        // Обработка аватара
        if ($request->hasFile('avatar')) {
            // Удаляем старый аватар
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatar = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $avatar->getClientOriginalExtension();

            // Создаем миниатюры
            $this->processAvatar($avatar, $filename);

            $validated['avatar'] = 'avatars/' . $filename;
        }

        $user->update($validated);

        return redirect()->route('profile.index')
            ->with('success', 'Профиль успешно обновлен.');
    }

    /**
     * Process and save avatar with thumbnails.
     */
    private function processAvatar($avatar, string $filename): void
    {
        // Основное изображение
        $image = Image::make($avatar);
        $image->fit(300, 300);
        Storage::disk('public')->put('avatars/' . $filename, $image->encode());

        // Миниатюра
        $thumbnail = Image::make($avatar);
        $thumbnail->fit(100, 100);
        Storage::disk('public')->put('avatars/thumbnails/' . $filename, $thumbnail->encode());
    }

    /**
     * Remove user avatar.
     */
    public function removeAvatar(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->avatar) {
            // Удаляем файлы аватара
            if (Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);

                $filename = basename($user->avatar);
                $thumbnailPath = 'avatars/thumbnails/' . $filename;

                if (Storage::disk('public')->exists($thumbnailPath)) {
                    Storage::disk('public')->delete($thumbnailPath);
                }
            }

            $user->update(['avatar' => null]);
        }

        return back()->with('success', 'Аватар удален.');
    }

    /**
     * Show change password form.
     */
    public function showChangePasswordForm(): View
    {
        return view('profile.change-password');
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        // Проверяем текущий пароль
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Текущий пароль неверен.']);
        }

        // Обновляем пароль
        $user->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        // Отправляем уведомление
        // Notification::send($user, new PasswordChanged($user));

        return back()->with('success', 'Пароль успешно изменен.');
    }

    /**
     * Display user bookings.
     */
    public function bookings(Request $request): View
    {
        $user = Auth::user();

        $query = $user->bookings()->with(['room', 'payment', 'review']);

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('check_in', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('check_out', '<=', $request->date_to);
        }

        // Сортировка
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        if (in_array($sort, ['check_in', 'check_out', 'created_at', 'total_price'])) {
            $query->orderBy($sort, $direction);
        }

        $bookings = $query->paginate(15);

        $statuses = [
            'pending' => 'Ожидает подтверждения',
            'confirmed' => 'Подтверждено',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено',
        ];

        return view('profile.bookings', compact('bookings', 'statuses'));
    }

    /**
     * Show booking details.
     */
    public function showBooking(Booking $booking): View
    {
        $user = Auth::user();

        // Проверяем, что бронирование принадлежит пользователю
        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        $booking->load(['room.photos', 'room.type', 'room.amenities', 'payment', 'review']);

        // Дополнительные услуги (если есть)
        $additionalServices = $booking->additional_services ?? [];

        // История изменений статуса
        $statusHistory = $booking->status_history ?? [];

        return view('profile.booking-show', compact('booking', 'additionalServices', 'statusHistory'));
    }

    /**
     * Cancel booking.
     */
    public function cancelBooking(Request $request, Booking $booking): RedirectResponse
    {
        $user = Auth::user();

        // Проверяем, что бронирование принадлежит пользователю
        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        // Проверяем статус бронирования
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->withErrors(['error' => 'Невозможно отменить бронирование с текущим статусом.']);
        }

        // Проверяем срок отмены
        $checkInDate = $booking->check_in;
        $daysUntilCheckIn = now()->diffInDays($checkInDate, false);

        // Политика отмены: можно отменить за 30 дней бесплатно
        if ($daysUntilCheckIn < 30) {
            return back()->withErrors([
                'error' => 'Отмена возможна только за 30 дней до заезда. '
                    . 'Для отмены позже этого срока свяжитесь с администрацией.'
            ]);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        // Обновляем статус бронирования
        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_at' => now(),
            'cancelled_by_user' => true,
        ]);

        // Возвращаем средства если платеж был
        if ($booking->payment && $booking->payment->status === 'completed') {
            $booking->payment->update([
                'status' => 'refunded',
                'refund_reason' => 'Отмена бронирования пользователем',
                'refunded_at' => now(),
            ]);
        }

        // Отправляем уведомление администратору
        // Notification::sendAdmin(new BookingCancelledByUser($booking));

        return redirect()->route('profile.bookings')
            ->with('success', 'Бронирование успешно отменено.');
    }

    /**
     * Download booking invoice.
     */
    public function downloadInvoice(Booking $booking)
    {
        $user = Auth::user();

        // Проверяем, что бронирование принадлежит пользователю
        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        // Проверяем, что платеж завершен
        if (!$booking->payment || $booking->payment->status !== 'completed') {
            return back()->withErrors(['error' => 'Счет доступен только для оплаченных бронирований.']);
        }

        // Генерируем PDF счет
        // $pdf = PDF::loadView('pdf.invoice', compact('booking', 'user'));

        // return $pdf->download('invoice-' . $booking->id . '.pdf');

        return back()->with('info', 'Функция скачивания счета временно недоступна.');
    }

    /**
     * Request booking modification.
     */
    public function requestBookingModification(Request $request, Booking $booking): RedirectResponse
    {
        $user = Auth::user();

        // Проверяем, что бронирование принадлежит пользователю
        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        // Проверяем статус бронирования
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->withErrors(['error' => 'Невозможно изменить бронирование с текущим статусом.']);
        }

        $validated = $request->validate([
            'new_check_in' => 'required|date|after_or_equal:today',
            'new_check_out' => 'required|date|after:new_check_in',
            'new_guests_count' => 'nullable|integer|min:1',
            'modification_reason' => 'required|string|max:500',
        ]);

        // Проверяем доступность номера на новые даты
        $isAvailable = Booking::where('room_id', $booking->room_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('check_in', [$validated['new_check_in'], $validated['new_check_out']])
                    ->orWhereBetween('check_out', [$validated['new_check_in'], $validated['new_check_out']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('check_in', '<', $validated['new_check_in'])
                            ->where('check_out', '>', $validated['new_check_out']);
                    });
            })
            ->doesntExist();

        if (!$isAvailable) {
            return back()->withErrors(['error' => 'Номер недоступен на выбранные даты.']);
        }

        // Создаем запрос на изменение
        $modificationRequest = $booking->modificationRequests()->create([
            'user_id' => $user->id,
            'new_check_in' => $validated['new_check_in'],
            'new_check_out' => $validated['new_check_out'],
            'new_guests_count' => $validated['new_guests_count'] ?? $booking->guests_count,
            'reason' => $validated['modification_reason'],
            'status' => 'pending',
        ]);

        // Отправляем уведомление администратору
        // Notification::sendAdmin(new BookingModificationRequest($modificationRequest));

        return back()->with('success', 'Запрос на изменение бронирования отправлен администратору.');
    }

    /**
     * Display user payments.
     */
    public function payments(Request $request): View
    {
        $user = Auth::user();

        $query = $user->payments()->with('booking');

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('payment_system')) {
            $query->where('payment_system', $request->payment_system);
        }

        $payments = $query->orderBy('payment_date', 'desc')->paginate(15);

        return view('profile.payments', compact('payments'));
    }

    /**
     * Display payment details.
     */
    public function showPayment(Payment $payment): View
    {
        $user = Auth::user();

        // Проверяем, что платеж принадлежит пользователю
        if ($payment->user_id !== $user->id) {
            abort(403);
        }

        $payment->load(['booking.room', 'refunds']);

        return view('profile.payment-show', compact('payment'));
    }

    /**
     * Display user reviews.
     */
    public function reviews(Request $request): View
    {
        $user = Auth::user();

        $query = $user->reviews()->with('booking.room');

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->orderBy('created_at', 'desc')->paginate(15);

        $statuses = [
            'pending' => 'На модерации',
            'approved' => 'Опубликовано',
            'rejected' => 'Отклонено',
        ];

        return view('profile.reviews', compact('reviews', 'statuses'));
    }

    /**
     * Edit review.
     */
    public function editReview(Review $review): View
    {
        $user = Auth::user();

        // Проверяем, что отзыв принадлежит пользователю
        if ($review->user_id !== $user->id) {
            abort(403);
        }

        // Проверяем, можно ли редактировать
        if ($review->status === 'approved' && $review->created_at->diffInHours(now()) > 24) {
            abort(403, 'Редактирование отзыва возможно только в течение 24 часов после публикации.');
        }

        return view('profile.review-edit', compact('review'));
    }

    /**
     * Update review.
     */
    public function updateReview(Request $request, Review $review): RedirectResponse
    {
        $user = Auth::user();

        // Проверяем, что отзыв принадлежит пользователю
        if ($review->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string|min:10|max:2000',
            'pros' => 'nullable|string|max:500',
            'cons' => 'nullable|string|max:500',
        ]);

        $review->update([
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'comment' => $validated['comment'],
            'pros' => $validated['pros'],
            'cons' => $validated['cons'],
            'is_edited' => true,
            'edited_at' => now(),
            'status' => 'pending', // Снова на модерацию после редактирования
        ]);

        return redirect()->route('profile.reviews')
            ->with('success', 'Отзыв обновлен и отправлен на модерацию.');
    }

    /**
     * Delete review.
     */
    public function deleteReview(Review $review): RedirectResponse
    {
        $user = Auth::user();

        // Проверяем, что отзыв принадлежит пользователю
        if ($review->user_id !== $user->id) {
            abort(403);
        }

        $review->delete();

        return redirect()->route('profile.reviews')
            ->with('success', 'Отзыв удален.');
    }

    /**
     * Display notifications.
     */
    public function notifications(Request $request): View
    {
        $user = Auth::user();

        $query = $user->notifications();

        // Фильтры
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('read')) {
            if ($request->read === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($request->read === 'unread') {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        // Помечаем как прочитанные при просмотре
        if ($request->has('mark_as_read') && $request->mark_as_read === 'all') {
            $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
            return redirect()->route('profile.notifications');
        }

        return view('profile.notifications', compact('notifications'));
    }

    /**
     * Mark notification as read.
     */
    public function markNotificationAsRead(Notification $notification): RedirectResponse
    {
        $user = Auth::user();

        // Проверяем, что уведомление принадлежит пользователю
        if ($notification->user_id !== $user->id) {
            abort(403);
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        // Редирект если есть ссылка
        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsAsRead(): RedirectResponse
    {
        $user = Auth::user();

        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Все уведомления помечены как прочитанные.');
    }

    /**
     * Delete notification.
     */
    public function deleteNotification(Notification $notification): RedirectResponse
    {
        $user = Auth::user();

        // Проверяем, что уведомление принадлежит пользователю
        if ($notification->user_id !== $user->id) {
            abort(403);
        }

        $notification->delete();

        return back()->with('success', 'Уведомление удалено.');
    }

    /**
     * Clear all notifications.
     */
    public function clearAllNotifications(): RedirectResponse
    {
        $user = Auth::user();

        $user->notifications()->delete();

        return back()->with('success', 'Все уведомления удалены.');
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'telegram_notifications' => 'nullable|boolean',
            'booking_confirmation' => 'nullable|boolean',
            'booking_reminder' => 'nullable|boolean',
            'payment_notifications' => 'nullable|boolean',
            'marketing_emails' => 'nullable|boolean',
            'newsletter' => 'nullable|boolean',
        ]);

        $user->update([
            'notification_preferences' => $validated,
        ]);

        return back()->with('success', 'Настройки уведомлений обновлены.');
    }

    /**
     * Display favorite rooms.
     */
    public function favorites(): View
    {
        $user = Auth::user();

        $favorites = $user->favoriteRooms()
            ->with(['photos', 'type', 'amenities'])
            ->paginate(12);

        return view('profile.favorites', compact('favorites'));
    }

    /**
     * Add room to favorites.
     */
    public function addToFavorites(Room $room): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->favoriteRooms()->where('room_id', $room->id)->exists()) {
            $user->favoriteRooms()->attach($room->id);

            return back()->with('success', 'Номер добавлен в избранное.');
        }

        return back()->with('info', 'Номер уже в избранном.');
    }

    /**
     * Remove room from favorites.
     */
    public function removeFromFavorites(Room $room): RedirectResponse
    {
        $user = Auth::user();

        $user->favoriteRooms()->detach($room->id);

        return back()->with('success', 'Номер удален из избранного.');
    }

    /**
     * Display security settings.
     */
    public function security(): View
    {
        $user = Auth::user();

        $sessions = $this->getActiveSessions($user);

        return view('profile.security', compact('user', 'sessions'));
    }

    /**
     * Get active sessions for user.
     */
    private function getActiveSessions(User $user): array
    {
        // Здесь должна быть логика получения активных сессий
        // Например, из таблицы активных сессий или через Laravel Sanctum

        return [
            [
                'id' => session()->getId(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'last_activity' => now(),
                'is_current' => true,
            ]
        ];
    }

    /**
     * Logout from other devices.
     */
    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|current_password',
        ]);

        // Здесь должна быть логика выхода с других устройств
        // Например, через Laravel Sanctum или инвалидацию других сессий

        return back()->with('success', 'Вы вышли со всех других устройств.');
    }

    /**
     * Display connected accounts (OAuth).
     */
    public function connectedAccounts(): View
    {
        $user = Auth::user();

        $connectedAccounts = [
            'google' => !empty($user->google_id),
            'facebook' => !empty($user->facebook_id),
            'github' => !empty($user->github_id),
            'vkontakte' => !empty($user->vkontakte_id),
        ];

        return view('profile.connected-accounts', compact('connectedAccounts'));
    }

    /**
     * Disconnect OAuth account.
     */
    public function disconnectAccount(Request $request, string $provider): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'password' => 'required_if:has_password,true|current_password',
        ]);

        $field = $provider . '_id';

        if (empty($user->$field)) {
            return back()->with('info', 'Аккаунт ' . $provider . ' не подключен.');
        }

        // Проверяем, что у пользователя есть пароль или другой способ входа
        $hasPassword = !empty($user->password);
        $hasOtherProviders = collect(['google_id', 'facebook_id', 'github_id', 'vkontakte_id'])
            ->filter(function ($field) use ($user, $provider) {
                return $field !== $provider . '_id' && !empty($user->$field);
            })
            ->isNotEmpty();

        if (!$hasPassword && !$hasOtherProviders) {
            return back()->withErrors([
                'error' => 'Нельзя отключить единственный способ входа. '
                    . 'Сначала установите пароль или подключите другой аккаунт.'
            ]);
        }

        $user->update([$field => null]);

        return back()->with('success', 'Аккаунт ' . $provider . ' отключен.');
    }

    /**
     * Display delete account form.
     */
    public function showDeleteAccountForm(): View
    {
        $user = Auth::user();

        // Проверяем активные бронирования
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->count();

        return view('profile.delete-account', compact('activeBookings'));
    }

    /**
     * Delete user account.
     */
    public function deleteAccount(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'password' => 'required|current_password',
            'reason' => 'nullable|string|max:500',
            'delete_all_data' => 'nullable|boolean',
        ]);

        // Проверяем активные бронирования
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->count();

        if ($activeBookings > 0) {
            return back()->withErrors([
                'error' => 'Нельзя удалить аккаунт с активными бронированиями. '
                    . 'Сначала отмените или завершите все бронирования.'
            ]);
        }

        // Анонимизируем или удаляем данные
        if ($request->has('delete_all_data') && $request->delete_all_data) {
            // Удаляем все данные пользователя
            $this->deleteUserData($user);
            $user->delete();
        } else {
            // Анонимизируем данные (GDPR compliance)
            $this->anonymizeUserData($user);
        }

        // Выход из системы
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Ваш аккаунт успешно удален. Надеемся увидеть вас снова!');
    }

    /**
     * Anonymize user data.
     */
    private function anonymizeUserData(User $user): void
    {
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@example.com',
            'phone' => null,
            'password' => Hash::make(Str::random(40)),
            'avatar' => null,
            'country' => null,
            'city' => null,
            'address' => null,
            'postal_code' => null,
            'date_of_birth' => null,
            'gender' => null,
            'preferences' => [],
            'notification_preferences' => [],
            'marketing_consent' => false,
            'telegram_chat_id' => null,
            'google_id' => null,
            'facebook_id' => null,
            'github_id' => null,
            'vkontakte_id' => null,
            'status' => 'inactive',
            'deleted_at' => now(),
            'anonymized_at' => now(),
        ]);
    }

    /**
     * Delete all user data.
     */
    private function deleteUserData(User $user): void
    {
        // Удаляем связанные данные
        $user->bookings()->delete();
        $user->reviews()->delete();
        $user->notifications()->delete();
        $user->payments()->delete();
        $user->favoriteRooms()->detach();

        // Удаляем пользователя (каскадно удалит остальное)
        $user->forceDelete();
    }

    /**
     * Export user data (GDPR).
     */
    public function exportData()
    {
        $user = Auth::user();

        // Собираем все данные пользователя
        $data = [
            'user' => $user->toArray(),
            'bookings' => $user->bookings()->with(['room', 'payment'])->get()->toArray(),
            'payments' => $user->payments()->with('booking')->get()->toArray(),
            'reviews' => $user->reviews()->with('booking.room')->get()->toArray(),
            'notifications' => $user->notifications()->get()->toArray(),
            'favorites' => $user->favoriteRooms()->get()->toArray(),
        ];

        // Генерируем JSON файл
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'user-data-' . $user->id . '-' . date('Y-m-d') . '.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Display referral program.
     */
    public function referral(): View
    {
        $user = Auth::user();

        // Генерируем реферальную ссылку если её нет
        if (!$user->referral_code) {
            $user->update(['referral_code' => Str::random(10)]);
            $user->refresh();
        }

        $referralStats = [
            'total_referred' => User::where('referred_by', $user->id)->count(),
            'active_referred' => User::where('referred_by', $user->id)
                ->where('status', 'active')
                ->count(),
            'total_earned' => $user->referral_earnings ?? 0,
            'pending_earnings' => $user->pending_referral_earnings ?? 0,
        ];

        $referredUsers = User::where('referred_by', $user->id)
            ->select(['id', 'name', 'email', 'created_at', 'status'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('profile.referral', compact('user', 'referralStats', 'referredUsers'));
    }

    /**
     * Display activity log.
     */
    public function activity(): View
    {
        $user = Auth::user();

        $activities = \App\Models\ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('profile.activity', compact('activities'));
    }
}
