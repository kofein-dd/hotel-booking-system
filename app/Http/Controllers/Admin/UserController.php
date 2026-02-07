<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-users')) {
            abort(403);
        }

        $query = User::where('role', 'user');

        // Фильтры
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('status', 'active');
            } elseif ($request->status === 'inactive') {
                $query->where('status', 'inactive');
            } elseif ($request->status === 'banned') {
                $query->whereNotNull('banned_until')
                    ->where('banned_until', '>', now());
            } elseif ($request->status === 'unconfirmed') {
                $query->whereNull('email_verified_at');
            }
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
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
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('has_bookings')) {
            if ($request->has_bookings === 'yes') {
                $query->has('bookings');
            } elseif ($request->has_bookings === 'no') {
                $query->doesntHave('bookings');
            }
        }

        if ($request->filled('min_bookings')) {
            $query->has('bookings', '>=', $request->min_bookings);
        }

        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        // Сортировка
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSortFields = ['name', 'email', 'created_at', 'last_login_at', 'bookings_count'];
        if (in_array($sortField, $allowedSortFields)) {
            if ($sortField === 'bookings_count') {
                $query->withCount('bookings')->orderBy('bookings_count', $sortDirection);
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        }

        $users = $query->paginate(30);

        // Статистика для отображения
        $stats = [
            'total' => User::where('role', 'user')->count(),
            'active' => User::where('role', 'user')->where('status', 'active')->count(),
            'inactive' => User::where('role', 'user')->where('status', 'inactive')->count(),
            'banned' => User::where('role', 'user')
                ->whereNotNull('banned_until')
                ->where('banned_until', '>', now())
                ->count(),
            'unconfirmed' => User::where('role', 'user')->whereNull('email_verified_at')->count(),
            'new_today' => User::where('role', 'user')->whereDate('created_at', today())->count(),
        ];

        // Страны пользователей
        $countries = User::where('role', 'user')
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return view('admin.users.index', compact('users', 'stats', 'countries'));
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): View
    {
        if (!Gate::allows('view-user', $user)) {
            abort(403);
        }

        $user->loadCount(['bookings', 'reviews', 'payments']);

        // Последние бронирования
        $recentBookings = $user->bookings()
            ->with(['room', 'payment'])
            ->latest()
            ->limit(10)
            ->get();

        // Последние платежи
        $recentPayments = $user->payments()
            ->with('booking')
            ->latest()
            ->limit(10)
            ->get();

        // Последние отзывы
        $recentReviews = $user->reviews()
            ->with('booking.room')
            ->latest()
            ->limit(10)
            ->get();

        // Статистика пользователя
        $userStats = $this->getUserStatistics($user);

        // Активность пользователя
        $activityLog = $this->getUserActivity($user);

        return view('admin.users.show', compact(
            'user',
            'recentBookings',
            'recentPayments',
            'recentReviews',
            'userStats',
            'activityLog'
        ));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        if (!Gate::allows('create-users')) {
            abort(403);
        }

        return view('admin.users.create');
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!Gate::allows('create-users')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,admin,moderator',
            'status' => 'required|in:active,inactive',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferences' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            'send_welcome_email' => 'nullable|boolean',
        ]);

        // Создаем пользователя
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'country' => $validated['country'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
            'date_of_birth' => $validated['date_of_birth'],
            'gender' => $validated['gender'],
            'preferences' => $validated['preferences'] ?? [],
            'notes' => $validated['notes'],
            'email_verified_at' => now(), // Админ создает - сразу подтвержден
            'registered_by_admin' => true,
            'registered_by' => auth()->guard('admin')->id(),
        ]);

        // Отправляем приветственное письмо
        if ($request->has('send_welcome_email') && $request->send_welcome_email) {
            // Mail::to($user->email)->send(new WelcomeEmail($user, $validated['password']));
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Пользователь успешно создан.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        if (!Gate::allows('edit-user', $user)) {
            abort(403);
        }

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        if (!Gate::allows('edit-user', $user)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:user,admin,moderator',
            'status' => 'required|in:active,inactive',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferences' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            'telegram_chat_id' => 'nullable|string|max:100',
            'notification_preferences' => 'nullable|array',
            'marketing_consent' => 'nullable|boolean',
        ]);

        // Если меняем email, сбрасываем подтверждение
        if ($user->email !== $validated['email']) {
            $validated['email_verified_at'] = null;
        }

        $user->update($validated);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Данные пользователя обновлены.');
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        if (!Gate::allows('edit-user', $user)) {
            abort(403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'notify_user' => 'nullable|boolean',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
            'password_changed_by_admin' => true,
            'password_changed_by' => auth()->guard('admin')->id(),
        ]);

        // Уведомляем пользователя
        if ($request->has('notify_user') && $request->notify_user) {
            // Notification::send($user, new PasswordChangedByAdmin());
        }

        return back()->with('success', 'Пароль пользователя обновлен.');
    }

    /**
     * Ban user.
     */
    public function ban(Request $request, User $user): RedirectResponse
    {
        if (!Gate::allows('ban-users')) {
            abort(403);
        }

        if ($user->isBanned()) {
            return back()->with('warning', 'Пользователь уже забанен.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'duration' => 'required|in:1,7,30,90,365,permanent',
            'notify_user' => 'nullable|boolean',
        ]);

        $bannedUntil = match($validated['duration']) {
            '1' => now()->addDay(),
            '7' => now()->addDays(7),
            '30' => now()->addDays(30),
            '90' => now()->addDays(90),
            '365' => now()->addDays(365),
            'permanent' => null, // null означает перманентный бан
            default => now()->addDays(30),
        };

        $user->update([
            'banned_until' => $bannedUntil,
            'ban_reason' => $validated['reason'],
            'banned_by' => auth()->guard('admin')->id(),
            'banned_at' => now(),
        ]);

        // Отменяем активные бронирования
        $this->cancelActiveBookings($user, $validated['reason']);

        // Уведомляем пользователя
        if ($request->has('notify_user') && $request->notify_user) {
            // Notification::send($user, new UserBanned($validated['reason'], $bannedUntil));
        }

        return back()->with('success', 'Пользователь забанен.');
    }

    /**
     * Unban user.
     */
    public function unban(User $user): RedirectResponse
    {
        if (!Gate::allows('ban-users')) {
            abort(403);
        }

        if (!$user->isBanned()) {
            return back()->with('warning', 'Пользователь не забанен.');
        }

        $user->update([
            'banned_until' => null,
            'ban_reason' => null,
            'banned_by' => null,
            'unbanned_by' => auth()->guard('admin')->id(),
            'unbanned_at' => now(),
        ]);

        // Уведомляем пользователя
        // Notification::send($user, new UserUnbanned());

        return back()->with('success', 'Пользователь разбанен.');
    }

    /**
     * Cancel active bookings for banned user.
     */
    private function cancelActiveBookings(User $user, string $reason): void
    {
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->get();

        foreach ($activeBookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => "Отменено администратором: {$reason}",
                'cancelled_at' => now(),
                'cancelled_by_admin' => true,
            ]);

            // Возвращаем средства если нужно
            if ($booking->payment && $booking->payment->status === 'completed') {
                $booking->payment->update([
                    'status' => 'refunded',
                    'refund_reason' => $reason,
                ]);
            }
        }
    }

    /**
     * Verify user email.
     */
    public function verifyEmail(User $user): RedirectResponse
    {
        if (!Gate::allows('edit-user', $user)) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('warning', 'Email уже подтвержден.');
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verified_by' => auth()->guard('admin')->id(),
        ]);

        // Уведомляем пользователя
        // Notification::send($user, new EmailVerifiedByAdmin());

        return back()->with('success', 'Email пользователя подтвержден.');
    }

    /**
     * Send email verification reminder.
     */
    public function sendVerificationReminder(User $user): RedirectResponse
    {
        if (!Gate::allows('edit-user', $user)) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('warning', 'Email уже подтвержден.');
        }

        // Отправляем письмо с подтверждением
        // $user->sendEmailVerificationNotification();

        return back()->with('success', 'Письмо с подтверждением отправлено.');
    }

    /**
     * Delete user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if (!Gate::allows('delete-user', $user)) {
            abort(403);
        }

        $validated = $request->validate([
            'confirmation' => 'required|in:DELETE_USER',
            'delete_bookings' => 'nullable|boolean',
            'delete_reviews' => 'nullable|boolean',
            'anonymize_data' => 'nullable|boolean',
        ]);

        // Проверяем, есть ли активные бронирования
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->count();

        if ($activeBookings > 0) {
            return back()->withErrors([
                'error' => 'Нельзя удалить пользователя с активными бронированиями.'
            ]);
        }

        // Удаляем или анонимизируем данные
        if ($request->has('anonymize_data') && $request->anonymize_data) {
            $this->anonymizeUserData($user);
        } else {
            if ($request->has('delete_bookings') && $request->delete_bookings) {
                $user->bookings()->delete();
            }

            if ($request->has('delete_reviews') && $request->delete_reviews) {
                $user->reviews()->delete();
            }

            $user->delete();
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь удален.');
    }

    /**
     * Anonymize user data (GDPR compliance).
     */
    private function anonymizeUserData(User $user): void
    {
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@example.com',
            'phone' => null,
            'password' => Hash::make(Str::random(40)),
            'country' => null,
            'city' => null,
            'address' => null,
            'postal_code' => null,
            'date_of_birth' => null,
            'gender' => null,
            'preferences' => [],
            'notes' => null,
            'telegram_chat_id' => null,
            'notification_preferences' => [],
            'marketing_consent' => false,
            'status' => 'inactive',
            'deleted_at' => now(),
            'anonymized_at' => now(),
            'anonymized_by' => auth()->guard('admin')->id(),
        ]);
    }

    /**
     * Bulk actions for users.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        if (!Gate::allows('manage-users')) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,ban,delete,send_email',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'reason' => 'required_if:action,ban|nullable|string|max:500',
            'duration' => 'required_if:action,ban|nullable|in:1,7,30,90,365,permanent',
            'email_subject' => 'required_if:action,send_email|nullable|string|max:255',
            'email_message' => 'required_if:action,send_email|nullable|string|max:2000',
        ]);

        $processed = 0;
        $failed = 0;

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::find($userId);

                switch ($validated['action']) {
                    case 'activate':
                        $user->update(['status' => 'active']);
                        break;

                    case 'deactivate':
                        $user->update(['status' => 'inactive']);
                        break;

                    case 'ban':
                        $bannedUntil = match($validated['duration']) {
                            '1' => now()->addDay(),
                            '7' => now()->addDays(7),
                            '30' => now()->addDays(30),
                            '90' => now()->addDays(90),
                            '365' => now()->addDays(365),
                            'permanent' => null,
                            default => now()->addDays(30),
                        };

                        $user->update([
                            'banned_until' => $bannedUntil,
                            'ban_reason' => $validated['reason'],
                            'banned_by' => auth()->guard('admin')->id(),
                            'banned_at' => now(),
                        ]);
                        break;

                    case 'delete':
                        if (!$user->hasActiveBookings()) {
                            $user->delete();
                        } else {
                            throw new \Exception('User has active bookings');
                        }
                        break;

                    case 'send_email':
                        // Mail::to($user->email)->send(new CustomEmail(
                        //     $validated['email_subject'],
                        //     $validated['email_message']
                        // ));
                        break;
                }

                $processed++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error('Bulk action failed for user ' . $userId . ': ' . $e->getMessage());
            }
        }

        $message = "Обработано {$processed} пользователей.";
        if ($failed > 0) {
            $message .= " Не удалось обработать {$failed} пользователей.";
        }

        return back()->with('success', $message);
    }

    /**
     * Get user statistics.
     */
    private function getUserStatistics(User $user): array
    {
        $today = now();

        return [
            'total_bookings' => $user->bookings()->count(),
            'completed_bookings' => $user->bookings()->where('status', 'completed')->count(),
            'active_bookings' => $user->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>=', $today)
                ->count(),
            'total_spent' => $user->payments()
                ->where('status', 'completed')
                ->sum('amount'),
            'avg_booking_value' => $user->bookings()
                    ->where('status', 'completed')
                    ->avg('total_price') ?? 0,
            'reviews_count' => $user->reviews()->count(),
            'avg_rating' => $user->reviews()
                    ->where('status', 'approved')
                    ->avg('rating') ?? 0,
            'days_since_registration' => $user->created_at->diffInDays($today),
            'last_booking_days_ago' => $user->bookings()
                    ->latest()
                    ->first()
                    ?->created_at
                    ?->diffInDays($today) ?? 'Никогда',
        ];
    }

    /**
     * Get user activity log.
     */
    private function getUserActivity(User $user): array
    {
        $activities = [];

        // Бронирования
        $bookings = $user->bookings()
            ->with('room')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                return [
                    'type' => 'booking',
                    'icon' => 'calendar-check',
                    'color' => 'primary',
                    'title' => 'Новое бронирование',
                    'description' => "Забронирован номер #{$booking->room->id}",
                    'time' => $booking->created_at->diffForHumans(),
                    'link' => route('admin.bookings.show', $booking),
                ];
            });

        $activities = array_merge($activities, $bookings->toArray());

        // Платежи
        $payments = $user->payments()
            ->with('booking')
            ->where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($payment) {
                return [
                    'type' => 'payment',
                    'icon' => 'credit-card',
                    'color' => 'success',
                    'title' => 'Оплата',
                    'description' => "Оплачено {$payment->amount} руб.",
                    'time' => $payment->created_at->diffForHumans(),
                    'link' => route('admin.payments.show', $payment),
                ];
            });

        $activities = array_merge($activities, $payments->toArray());

        // Отзывы
        $reviews = $user->reviews()
            ->with('booking.room')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($review) {
                return [
                    'type' => 'review',
                    'icon' => 'star',
                    'color' => 'warning',
                    'title' => 'Новый отзыв',
                    'description' => "Оценка: {$review->rating} звезд",
                    'time' => $review->created_at->diffForHumans(),
                    'link' => route('admin.reviews.show', $review),
                ];
            });

        $activities = array_merge($activities, $reviews->toArray());

        // Входы в систему
        if ($user->last_login_at) {
            $activities[] = [
                'type' => 'login',
                'icon' => 'door-open',
                'color' => 'info',
                'title' => 'Вход в систему',
                'description' => 'Пользователь вошел в систему',
                'time' => $user->last_login_at->diffForHumans(),
                'link' => null,
            ];
        }

        // Сортируем по времени
        usort($activities, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Get user bookings.
     */
    public function bookings(User $user, Request $request): View
    {
        if (!Gate::allows('view-user', $user)) {
            abort(403);
        }

        $bookings = $user->bookings()
            ->with(['room', 'payment'])
            ->latest()
            ->paginate(20);

        return view('admin.users.bookings', compact('user', 'bookings'));
    }

    /**
     * Get user payments.
     */
    public function payments(User $user, Request $request): View
    {
        if (!Gate::allows('view-user', $user)) {
            abort(403);
        }

        $payments = $user->payments()
            ->with('booking')
            ->latest()
            ->paginate(20);

        return view('admin.users.payments', compact('user', 'payments'));
    }

    /**
     * Get user reviews.
     */
    public function reviews(User $user, Request $request): View
    {
        if (!Gate::allows('view-user', $user)) {
            abort(403);
        }

        $reviews = $user->reviews()
            ->with('booking.room')
            ->latest()
            ->paginate(20);

        return view('admin.users.reviews', compact('user', 'reviews'));
    }

    /**
     * Send message to user.
     */
    public function sendMessage(Request $request, User $user): RedirectResponse
    {
        if (!Gate::allows('message-users')) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'channel' => 'required|in:email,internal,both',
            'save_as_notification' => 'nullable|boolean',
        ]);

        // Отправляем email
        if (in_array($validated['channel'], ['email', 'both'])) {
            // Mail::to($user->email)->send(new AdminMessage(
            //     $validated['subject'],
            //     $validated['message']
            // ));
        }

        // Создаем внутреннее уведомление
        if (in_array($validated['channel'], ['internal', 'both']) ||
            ($request->has('save_as_notification') && $request->save_as_notification)) {

            Notification::create([
                'user_id' => $user->id,
                'title' => $validated['subject'],
                'message' => $validated['message'],
                'type' => 'admin_message',
                'channel' => 'internal',
                'sent_at' => now(),
                'sent_by' => auth()->guard('admin')->id(),
            ]);
        }

        return back()->with('success', 'Сообщение отправлено.');
    }

    /**
     * Export users data.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-users')) {
            abort(403);
        }

        $users = User::where('role', 'user')
            ->withCount(['bookings', 'reviews', 'payments'])
            ->withSum(['payments' => function ($query) {
                $query->where('status', 'completed');
            }], 'amount')
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->status === 'banned') {
                    $query->whereNotNull('banned_until')
                        ->where('banned_until', '>', now());
                } else {
                    $query->where('status', $request->status);
                }
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->where('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            })
            ->when($request->filled('has_bookings'), function ($query) use ($request) {
                if ($request->has_bookings === 'yes') {
                    $query->has('bookings');
                } elseif ($request->has_bookings === 'no') {
                    $query->doesntHave('bookings');
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID',
                'Имя',
                'Email',
                'Телефон',
                'Страна',
                'Город',
                'Статус',
                'Забанен до',
                'Email подтвержден',
                'Дата регистрации',
                'Последний вход',
                'Всего бронирований',
                'Всего потрачено',
                'Отзывов',
                'Примечания',
            ], ';');

            foreach ($users as $user) {
                $bannedInfo = $user->isBanned()
                    ? ($user->banned_until ? $user->banned_until->format('d.m.Y') : 'Перманентно')
                    : 'Нет';

                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone ?? '',
                    $user->country ?? '',
                    $user->city ?? '',
                    $user->status,
                    $bannedInfo,
                    $user->hasVerifiedEmail() ? 'Да' : 'Нет',
                    $user->created_at->format('d.m.Y H:i'),
                    $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'Никогда',
                    $user->bookings_count,
                    number_format($user->payments_sum_amount ?? 0, 2, '.', ''),
                    $user->reviews_count,
                    substr($user->notes ?? '', 0, 100),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import users from CSV.
     */
    public function import(): View
    {
        if (!Gate::allows('create-users')) {
            abort(403);
        }

        return view('admin.users.import');
    }

    /**
     * Process imported users.
     */
    public function processImport(Request $request): RedirectResponse
    {
        if (!Gate::allows('create-users')) {
            abort(403);
        }

        $validated = $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:2048',
            'send_welcome_emails' => 'nullable|boolean',
            'generate_passwords' => 'nullable|boolean',
        ]);

        try {
            $file = $request->file('import_file');
            $path = $file->store('temp');
            $fullPath = storage_path('app/' . $path);

            $imported = 0;
            $updated = 0;
            $failed = 0;

            if (($handle = fopen($fullPath, 'r')) !== false) {
                // Пропускаем заголовок
                fgetcsv($handle);

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    try {
                        // Формат: name,email,phone,country,city,status
                        $userData = [
                            'name' => $data[0] ?? '',
                            'email' => $data[1] ?? '',
                            'phone' => $data[2] ?? null,
                            'country' => $data[3] ?? null,
                            'city' => $data[4] ?? null,
                            'status' => $data[5] ?? 'active',
                            'role' => 'user',
                            'password' => Hash::make($request->generate_passwords ? Str::random(12) : 'temp_password'),
                            'email_verified_at' => now(),
                            'imported' => true,
                        ];

                        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                            throw new \Exception('Invalid email format');
                        }

                        // Проверяем существование пользователя
                        $existingUser = User::where('email', $userData['email'])->first();

                        if ($existingUser) {
                            $existingUser->update($userData);
                            $updated++;
                        } else {
                            User::create($userData);
                            $imported++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        \Log::error('Import user failed: ' . $e->getMessage());
                    }
                }
                fclose($handle);
            }

            // Удаляем временный файл
            unlink($fullPath);

            $message = "Импортировано {$imported} пользователей, обновлено {$updated}.";
            if ($failed > 0) {
                $message .= " Не удалось обработать {$failed} записей.";
            }

            // Отправляем приветственные письма
            if ($request->has('send_welcome_emails') && $request->send_welcome_emails) {
                // Логика отправки писем
            }

            return redirect()->route('admin.users.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка импорта: ' . $e->getMessage()]);
        }
    }

    /**
     * Get user statistics for dashboard.
     */
    public function dashboardWidget(): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('view-dashboard')) {
            abort(403);
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $newUsersToday = User::where('role', 'user')
            ->whereDate('created_at', $today)
            ->count();

        $newUsersYesterday = User::where('role', 'user')
            ->whereDate('created_at', $yesterday)
            ->count();

        $totalUsers = User::where('role', 'user')->count();
        $bannedUsers = User::where('role', 'user')
            ->whereNotNull('banned_until')
            ->where('banned_until', '>', now())
            ->count();

        $growthPercent = $newUsersYesterday > 0
            ? round((($newUsersToday - $newUsersYesterday) / $newUsersYesterday) * 100, 2)
            : ($newUsersToday > 0 ? 100 : 0);

        return response()->json([
            'new_today' => $newUsersToday,
            'new_yesterday' => $newUsersYesterday,
            'growth_percent' => $growthPercent,
            'total' => $totalUsers,
            'banned' => $bannedUsers,
        ]);
    }

    /**
     * Search users for autocomplete.
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('manage-users')) {
            abort(403);
        }

        $query = $request->get('q', '');

        $users = User::where('role', 'user')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json($users);
    }

    /**
     * Get user activity statistics.
     */
    public function activityStatistics(User $user): View
    {
        if (!Gate::allows('view-user', $user)) {
            abort(403);
        }

        // Активность по месяцам
        $monthlyActivity = Booking::where('user_id', $user->id)
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(total_price) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Предпочтения пользователя
        $preferredRoomTypes = DB::table('bookings')
            ->join('rooms', 'bookings.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.type_id', '=', 'room_types.id')
            ->select(
                'room_types.name',
                'room_types.id',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(bookings.total_price) as avg_price')
            )
            ->where('bookings.user_id', $user->id)
            ->where('bookings.status', 'completed')
            ->groupBy('room_types.id', 'room_types.name')
            ->orderBy('count', 'desc')
            ->get();

        // Сезонность бронирований
        $seasonality = Booking::where('user_id', $user->id)
            ->select(
                DB::raw('MONTH(check_in) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'completed')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('admin.users.activity-statistics', compact(
            'user',
            'monthlyActivity',
            'preferredRoomTypes',
            'seasonality'
        ));
    }

    /**
     * Merge duplicate users.
     */
    public function merge(Request $request): RedirectResponse
    {
        if (!Gate::allows('manage-users')) {
            abort(403);
        }

        $validated = $request->validate([
            'primary_user_id' => 'required|exists:users,id',
            'duplicate_user_ids' => 'required|array|min:1',
            'duplicate_user_ids.*' => 'exists:users,id',
            'merge_data' => 'required|array',
            'merge_data.*' => 'in:bookings,payments,reviews,notifications',
        ]);

        $primaryUser = User::findOrFail($validated['primary_user_id']);
        $duplicates = User::whereIn('id', $validated['duplicate_user_ids'])->get();

        DB::beginTransaction();

        try {
            foreach ($duplicates as $duplicate) {
                // Переносим бронирования
                if (in_array('bookings', $validated['merge_data'])) {
                    $duplicate->bookings()->update(['user_id' => $primaryUser->id]);
                }

                // Переносим платежи
                if (in_array('payments', $validated['merge_data'])) {
                    $duplicate->payments()->update(['user_id' => $primaryUser->id]);
                }

                // Переносим отзывы
                if (in_array('reviews', $validated['merge_data'])) {
                    $duplicate->reviews()->update(['user_id' => $primaryUser->id]);
                }

                // Переносим уведомления
                if (in_array('notifications', $validated['merge_data'])) {
                    $duplicate->notifications()->update(['user_id' => $primaryUser->id]);
                }

                // Удаляем дубликат
                $duplicate->delete();
            }

            DB::commit();

            return back()->with('success',
                "Пользователи успешно объединены. Основной пользователь: {$primaryUser->email}"
            );
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Merge users failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Ошибка объединения пользователей: ' . $e->getMessage()]);
        }
    }
}
