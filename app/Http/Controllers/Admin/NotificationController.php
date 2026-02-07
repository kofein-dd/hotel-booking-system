<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Mail\CustomNotificationMail;
use App\Services\TelegramNotificationService;
use App\Services\PushNotificationService;

class NotificationController extends Controller
{
    protected $telegramService;
    protected $pushService;

    public function __construct(
        TelegramNotificationService $telegramService,
        PushNotificationService $pushService
    ) {
        $this->telegramService = $telegramService;
        $this->pushService = $pushService;
    }

    /**
     * Display a listing of notifications.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-notifications')) {
            abort(403);
        }

        $query = Notification::query();

        // Фильтры
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'sent') {
                $query->whereNotNull('sent_at');
            } elseif ($request->status === 'pending') {
                $query->whereNull('sent_at')
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                            ->orWhere('scheduled_at', '<=', now());
                    });
            } elseif ($request->status === 'scheduled') {
                $query->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now());
            }
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('recipient_type')) {
            $query->where('recipient_type', $request->recipient_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        $notificationTypes = Notification::distinct()->pluck('type');
        $channels = Notification::distinct()->pluck('channel');

        return view('admin.notifications.index', compact('notifications', 'notificationTypes', 'channels'));
    }

    /**
     * Show the form for creating a new notification.
     */
    public function create(): View
    {
        if (!Gate::allows('create-notifications')) {
            abort(403);
        }

        $users = User::where('role', 'user')->get();
        $bookings = Booking::whereIn('status', ['confirmed', 'pending'])
            ->with(['user', 'room'])
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.notifications.create', compact('users', 'bookings'));
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!Gate::allows('create-notifications')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'type' => 'required|in:system,email,telegram,push,all',
            'channel' => 'required|in:all,email,telegram,push,internal',
            'recipient_type' => 'required|in:all_users,specific_users,users_with_bookings,booking_specific',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'booking_ids' => 'nullable|array',
            'booking_ids.*' => 'exists:bookings,id',
            'scheduled_at' => 'nullable|date|after_or_equal:now',
            'priority' => 'required|in:low,normal,high,urgent',
            'is_important' => 'nullable|boolean',
            'action_url' => 'nullable|url|max:500',
            'action_text' => 'nullable|string|max:100',
            'template' => 'nullable|string|max:100',
        ]);

        // Определяем получателей
        $recipients = $this->getRecipients($validated);

        if (empty($recipients)) {
            return back()->withErrors(['recipient_type' => 'Не найдено получателей для уведомления.']);
        }

        // Создаем уведомление для каждого получателя
        $createdCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $notificationData = [
                    'user_id' => $recipient->id,
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'type' => $validated['type'],
                    'channel' => $validated['channel'],
                    'recipient_type' => $validated['recipient_type'],
                    'priority' => $validated['priority'],
                    'is_important' => $validated['is_important'] ?? false,
                    'action_url' => $validated['action_url'],
                    'action_text' => $validated['action_text'],
                    'template' => $validated['template'],
                    'scheduled_at' => $validated['scheduled_at'],
                    'metadata' => [
                        'booking_ids' => $validated['booking_ids'] ?? null,
                        'created_by' => auth()->guard('admin')->id(),
                    ],
                ];

                // Если указан конкретный тип канала, создаем отдельные уведомления
                if ($validated['channel'] === 'all') {
                    $channels = ['email', 'telegram', 'push', 'internal'];
                    foreach ($channels as $channel) {
                        $notificationData['channel'] = $channel;
                        Notification::create($notificationData);
                        $createdCount++;
                    }
                } else {
                    Notification::create($notificationData);
                    $createdCount++;
                }
            } catch (\Exception $e) {
                $failedCount++;
                \Log::error('Failed to create notification: ' . $e->getMessage());
            }
        }

        $message = "Создано {$createdCount} уведомлений.";
        if ($failedCount > 0) {
            $message .= " Не удалось создать {$failedCount} уведомлений.";
        }

        // Если уведомление не запланировано - отправляем сразу
        if (!$validated['scheduled_at'] || Carbon::parse($validated['scheduled_at'])->lte(now())) {
            $this->dispatchNotifications($validated['channel'], $recipients);
        }

        return redirect()->route('admin.notifications.index')
            ->with('success', $message);
    }

    /**
     * Get recipients based on recipient type.
     */
    private function getRecipients(array $data): array
    {
        $recipients = [];

        switch ($data['recipient_type']) {
            case 'all_users':
                $recipients = User::where('role', 'user')
                    ->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('banned_until')
                            ->orWhere('banned_until', '<', now());
                    })
                    ->get();
                break;

            case 'specific_users':
                if (!empty($data['user_ids'])) {
                    $recipients = User::whereIn('id', $data['user_ids'])
                        ->where('status', 'active')
                        ->get();
                }
                break;

            case 'users_with_bookings':
                $recipients = User::whereHas('bookings', function ($query) {
                    $query->whereIn('status', ['confirmed', 'pending']);
                })
                    ->where('status', 'active')
                    ->distinct()
                    ->get();
                break;

            case 'booking_specific':
                if (!empty($data['booking_ids'])) {
                    $bookings = Booking::whereIn('id', $data['booking_ids'])
                        ->with('user')
                        ->get();

                    $recipients = $bookings->pluck('user')->unique()->filter();
                }
                break;
        }

        return $recipients;
    }

    /**
     * Display the specified notification.
     */
    public function show(Notification $notification): View
    {
        if (!Gate::allows('view-notification', $notification)) {
            abort(403);
        }

        $notification->load(['user', 'booking']);

        // Статистика доставки
        $deliveryStats = $this->getDeliveryStats($notification);

        return view('admin.notifications.show', compact('notification', 'deliveryStats'));
    }

    /**
     * Get delivery statistics for notification.
     */
    private function getDeliveryStats(Notification $notification): array
    {
        if (!$notification->sent_at) {
            return ['status' => 'pending', 'message' => 'Ожидает отправки'];
        }

        $stats = [
            'status' => 'sent',
            'sent_at' => $notification->sent_at->format('d.m.Y H:i'),
            'delivery_time' => $notification->sent_at->diff($notification->created_at)->format('%H:%I:%S'),
        ];

        // Для email уведомлений
        if ($notification->channel === 'email') {
            $stats['opened'] = $notification->opened_at ? $notification->opened_at->format('d.m.Y H:i') : 'Не открыто';
            $stats['clicked'] = $notification->clicked_at ? $notification->clicked_at->format('d.m.Y H:i') : 'Не нажато';
        }

        // Для push уведомлений
        if ($notification->channel === 'push') {
            $stats['delivered'] = $notification->metadata['delivered'] ?? false;
            $stats['clicked'] = $notification->metadata['clicked'] ?? false;
        }

        return $stats;
    }

    /**
     * Send notification immediately.
     */
    public function send(Notification $notification): RedirectResponse
    {
        if (!Gate::allows('send-notifications')) {
            abort(403);
        }

        if ($notification->sent_at) {
            return back()->with('warning', 'Уведомление уже отправлено.');
        }

        try {
            $this->sendNotification($notification);

            $notification->update([
                'sent_at' => now(),
                'status' => 'sent',
            ]);

            return back()->with('success', 'Уведомление отправлено.');
        } catch (\Exception $e) {
            \Log::error('Failed to send notification: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Ошибка отправки: ' . $e->getMessage()]);
        }
    }

    /**
     * Send multiple notifications.
     */
    public function sendBatch(Request $request): RedirectResponse
    {
        if (!Gate::allows('send-notifications')) {
            abort(403);
        }

        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id',
        ]);

        $sentCount = 0;
        $failedCount = 0;

        foreach ($request->notification_ids as $notificationId) {
            try {
                $notification = Notification::find($notificationId);

                if ($notification && !$notification->sent_at) {
                    $this->sendNotification($notification);

                    $notification->update([
                        'sent_at' => now(),
                        'status' => 'sent',
                    ]);

                    $sentCount++;
                }
            } catch (\Exception $e) {
                $failedCount++;
                \Log::error('Failed to send batch notification: ' . $e->getMessage());
            }
        }

        $message = "Отправлено {$sentCount} уведомлений.";
        if ($failedCount > 0) {
            $message .= " Не удалось отправить {$failedCount} уведомлений.";
        }

        return back()->with('success', $message);
    }

    /**
     * Send notification via appropriate channel.
     */
    private function sendNotification(Notification $notification): void
    {
        switch ($notification->channel) {
            case 'email':
                $this->sendEmailNotification($notification);
                break;

            case 'telegram':
                $this->sendTelegramNotification($notification);
                break;

            case 'push':
                $this->sendPushNotification($notification);
                break;

            case 'internal':
                // Для внутренних уведомлений просто помечаем как отправленные
                break;
        }
    }

    /**
     * Send email notification.
     */
    private function sendEmailNotification(Notification $notification): void
    {
        if (!$notification->user || !$notification->user->email) {
            throw new \Exception('No email address for user');
        }

        Mail::to($notification->user->email)
            ->send(new CustomNotificationMail($notification));
    }

    /**
     * Send Telegram notification.
     */
    private function sendTelegramNotification(Notification $notification): void
    {
        if (!$notification->user || !$notification->user->telegram_chat_id) {
            throw new \Exception('No Telegram chat ID for user');
        }

        $message = "📢 *{$notification->title}*\n\n"
            . "{$notification->message}\n\n";

        if ($notification->action_url) {
            $message .= "[{$notification->action_text}]($notification->action_url)";
        }

        $this->telegramService->sendToUser(
            $notification->user->telegram_chat_id,
            $message,
            'markdown'
        );
    }

    /**
     * Send push notification.
     */
    private function sendPushNotification(Notification $notification): void
    {
        if (!$notification->user) {
            throw new \Exception('No user for push notification');
        }

        $this->pushService->send(
            $notification->user,
            $notification->title,
            $notification->message,
            [
                'action_url' => $notification->action_url,
                'notification_id' => $notification->id,
            ]
        );
    }

    /**
     * Dispatch notifications to recipients.
     */
    private function dispatchNotifications(string $channel, $recipients): void
    {
        // Здесь можно добавить логику массовой отправки через очереди
        foreach ($recipients as $recipient) {
            // Отправка через соответствующий канал
            // ...
        }
    }

    /**
     * Show the form for editing the specified notification.
     */
    public function edit(Notification $notification): View
    {
        if (!Gate::allows('edit-notification', $notification)) {
            abort(403);
        }

        $users = User::where('role', 'user')->get();
        $bookings = Booking::whereIn('status', ['confirmed', 'pending'])
            ->with(['user', 'room'])
            ->latest()
            ->limit(50)
            ->get();

        $selectedUsers = $notification->user_id ? [$notification->user_id] : [];

        return view('admin.notifications.edit', compact('notification', 'users', 'bookings', 'selectedUsers'));
    }

    /**
     * Update the specified notification.
     */
    public function update(Request $request, Notification $notification): RedirectResponse
    {
        if (!Gate::allows('edit-notification', $notification)) {
            abort(403);
        }

        if ($notification->sent_at) {
            return back()->withErrors(['error' => 'Нельзя редактировать отправленное уведомление.']);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'type' => 'required|in:system,email,telegram,push,all',
            'channel' => 'required|in:all,email,telegram,push,internal',
            'scheduled_at' => 'nullable|date|after_or_equal:now',
            'priority' => 'required|in:low,normal,high,urgent',
            'is_important' => 'nullable|boolean',
            'action_url' => 'nullable|url|max:500',
            'action_text' => 'nullable|string|max:100',
            'template' => 'nullable|string|max:100',
        ]);

        $notification->update($validated);

        return redirect()->route('admin.notifications.show', $notification)
            ->with('success', 'Уведомление обновлено.');
    }

    /**
     * Reschedule notification.
     */
    public function reschedule(Request $request, Notification $notification): RedirectResponse
    {
        if (!Gate::allows('edit-notification', $notification)) {
            abort(403);
        }

        if ($notification->sent_at) {
            return back()->withErrors(['error' => 'Нельзя перенести отправленное уведомление.']);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after_or_equal:now',
        ]);

        $notification->update([
            'scheduled_at' => $validated['scheduled_at'],
        ]);

        return back()->with('success', 'Уведомление перенесено.');
    }

    /**
     * Cancel scheduled notification.
     */
    public function cancel(Notification $notification): RedirectResponse
    {
        if (!Gate::allows('edit-notification', $notification)) {
            abort(403);
        }

        if ($notification->sent_at) {
            return back()->withErrors(['error' => 'Нельзя отменить отправленное уведомление.']);
        }

        $notification->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return back()->with('success', 'Уведомление отменено.');
    }

    /**
     * Delete the specified notification.
     */
    public function destroy(Notification $notification): RedirectResponse
    {
        if (!Gate::allows('delete-notification', $notification)) {
            abort(403);
        }

        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Уведомление удалено.');
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        if (!Gate::allows('delete-notifications')) {
            abort(403);
        }

        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id',
        ]);

        $deletedCount = Notification::whereIn('id', $request->notification_ids)
            ->whereNull('sent_at')
            ->delete();

        return back()->with('success', "Удалено {$deletedCount} уведомлений.");
    }

    /**
     * Get notification statistics.
     */
    public function statistics(): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Общая статистика
        $totalNotifications = Notification::count();
        $sentNotifications = Notification::whereNotNull('sent_at')->count();
        $pendingNotifications = Notification::whereNull('sent_at')->count();
        $scheduledNotifications = Notification::whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->count();

        // Статистика по каналам
        $channelStats = Notification::select('channel', DB::raw('COUNT(*) as count'))
            ->groupBy('channel')
            ->get()
            ->pluck('count', 'channel');

        // Статистика по типам
        $typeStats = Notification::select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        // Статистика по дням (последние 30 дней)
        $dailyStats = Notification::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Самые активные получатели
        $activeUsers = User::withCount(['notifications as notifications_count'])
            ->has('notifications')
            ->orderBy('notifications_count', 'desc')
            ->limit(10)
            ->get();

        // Эффективность доставки
        $deliveryRate = $totalNotifications > 0
            ? round(($sentNotifications / $totalNotifications) * 100, 2)
            : 0;

        // Время доставки (среднее)
        $avgDeliveryTime = Notification::whereNotNull('sent_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_seconds'))
            ->first()
            ->avg_seconds ?? 0;

        return view('admin.notifications.statistics', compact(
            'totalNotifications',
            'sentNotifications',
            'pendingNotifications',
            'scheduledNotifications',
            'channelStats',
            'typeStats',
            'dailyStats',
            'activeUsers',
            'deliveryRate',
            'avgDeliveryTime'
        ));
    }

    /**
     * Preview notification template.
     */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('create-notifications')) {
            abort(403);
        }

        $validated = $request->validate([
            'template' => 'required|string|max:100',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
            'channel' => 'required|in:email,telegram,push',
        ]);

        $preview = $this->generatePreview($validated);

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Generate preview for notification.
     */
    private function generatePreview(array $data): array
    {
        $preview = [
            'title' => $data['title'] ?? 'Заголовок уведомления',
            'message' => $data['message'] ?? 'Текст уведомления',
        ];

        switch ($data['channel']) {
            case 'email':
                $preview['html'] = view('emails.notifications.template', [
                    'title' => $preview['title'],
                    'message' => $preview['message'],
                    'action_url' => '#',
                    'action_text' => 'Перейти',
                ])->render();
                break;

            case 'telegram':
                $preview['text'] = "📢 *{$preview['title']}*\n\n"
                    . "{$preview['message']}\n\n"
                    . "[Перейти](#)";
                break;

            case 'push':
                $preview['push'] = [
                    'title' => $preview['title'],
                    'body' => $preview['message'],
                    'icon' => '/images/notification-icon.png',
                    'badge' => '/images/notification-badge.png',
                ];
                break;
        }

        return $preview;
    }

    /**
     * Get notification templates.
     */
    public function templates(): View
    {
        if (!Gate::allows('manage-notifications')) {
            abort(403);
        }

        $templates = [
            'booking_confirmation' => [
                'name' => 'Подтверждение бронирования',
                'description' => 'Отправляется при подтверждении бронирования',
                'subject' => 'Ваше бронирование подтверждено',
                'channel' => 'email',
            ],
            'booking_reminder' => [
                'name' => 'Напоминание о бронировании',
                'description' => 'Отправляется за N дней до заезда',
                'subject' => 'Напоминание о предстоящем заезде',
                'channel' => 'all',
            ],
            'payment_received' => [
                'name' => 'Оплата получена',
                'description' => 'Отправляется при успешной оплате',
                'subject' => 'Оплата получена',
                'channel' => 'email',
            ],
            'check_in_reminder' => [
                'name' => 'Напоминание о заезде',
                'description' => 'Отправляется в день заезда',
                'subject' => 'Добро пожаловать!',
                'channel' => 'push',
            ],
            'special_offer' => [
                'name' => 'Специальное предложение',
                'description' => 'Отправляется для продвижения акций',
                'subject' => 'Специальное предложение для вас',
                'channel' => 'all',
            ],
        ];

        return view('admin.notifications.templates', compact('templates'));
    }

    /**
     * Send test notification.
     */
    public function sendTest(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('create-notifications')) {
            abort(403);
        }

        $validated = $request->validate([
            'channel' => 'required|in:email,telegram,push',
            'email' => 'required_if:channel,email|email',
            'telegram_chat_id' => 'required_if:channel,telegram|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        try {
            switch ($validated['channel']) {
                case 'email':
                    Mail::to($validated['email'])
                        ->send(new CustomNotificationMail([
                            'title' => $validated['title'],
                            'message' => $validated['message'],
                        ]));
                    break;

                case 'telegram':
                    $this->telegramService->sendToUser(
                        $validated['telegram_chat_id'],
                        "📢 *Тестовое уведомление*\n\n{$validated['message']}",
                        'markdown'
                    );
                    break;

                case 'push':
                    // Тестовый push через сервис
                    $this->pushService->sendTest($validated['title'], $validated['message']);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Тестовое уведомление отправлено.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Test notification failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка отправки: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export notifications.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-notifications')) {
            abort(403);
        }

        $notifications = Notification::with(['user'])
            ->when($request->filled('channel'), function ($query) use ($request) {
                $query->where('channel', $request->channel);
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
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
            'Content-Disposition' => 'attachment; filename="notifications_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($notifications) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID', 'Получатель', 'Канал', 'Тип', 'Заголовок', 'Сообщение',
                'Приоритет', 'Запланировано', 'Отправлено', 'Статус', 'Создано'
            ]);

            foreach ($notifications as $notification) {
                fputcsv($file, [
                    $notification->id,
                    $notification->user ? $notification->user->email : 'Все',
                    $this->getChannelName($notification->channel),
                    $this->getTypeName($notification->type),
                    $notification->title,
                    substr($notification->message, 0, 100) . '...',
                    $this->getPriorityName($notification->priority),
                    $notification->scheduled_at ? $notification->scheduled_at->format('d.m.Y H:i') : '-',
                    $notification->sent_at ? $notification->sent_at->format('d.m.Y H:i') : '-',
                    $notification->status,
                    $notification->created_at->format('d.m.Y H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get channel name in Russian.
     */
    private function getChannelName(string $channel): string
    {
        return match($channel) {
            'email' => 'Email',
            'telegram' => 'Telegram',
            'push' => 'Push',
            'internal' => 'Внутреннее',
            'all' => 'Все каналы',
            default => $channel,
        };
    }

    /**
     * Get type name in Russian.
     */
    private function getTypeName(string $type): string
    {
        return match($type) {
            'system' => 'Системное',
            'booking' => 'Бронирование',
            'payment' => 'Платеж',
            'marketing' => 'Маркетинг',
            'reminder' => 'Напоминание',
            default => $type,
        };
    }

    /**
     * Get priority name in Russian.
     */
    private function getPriorityName(string $priority): string
    {
        return match($priority) {
            'low' => 'Низкий',
            'normal' => 'Обычный',
            'high' => 'Высокий',
            'urgent' => 'Срочный',
            default => $priority,
        };
    }
}
