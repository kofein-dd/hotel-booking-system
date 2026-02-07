<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class TestPushNotification extends Notification
{
    use Queueable;

    public string $testType;
    public array $testData;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $testType = 'system', array $testData = [])
    {
        $this->testType = $testType;
        $this->testData = $testData;
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'test_push_notification',
            'test_type' => $this->testType,
            'test_data' => $this->testData,
            'sent_at' => now()->toDateTimeString(),
            'device_info' => request()->header('User-Agent'),
            'action_url' => route('notifications.index'),
            'is_test' => true,
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        $title = match($this->testType) {
            'booking' => 'Тест: Уведомление о бронировании',
            'message' => 'Тест: Новое сообщение',
            'system' => 'Тест: Системное уведомление',
            'alert' => 'Тест: Важное уведомление',
            default => 'Тестовое push-уведомление',
        };

        $body = match($this->testType) {
            'booking' => 'Это тестовое уведомление о бронировании. Всё работает корректно!',
            'message' => 'Тестовое сообщение для проверки работы чата.',
            'system' => 'Проверка системы уведомлений выполнена успешно.',
            'alert' => 'Внимание! Это тестовое оповещение.',
            default => 'Тестовое уведомление отправлено ' . now()->format('H:i:s'),
        };

        return WebPushMessage::create()
            ->id($notification->id)
            ->title($title)
            ->icon('/images/logo.png')
            ->badge('/images/badge.png')
            ->body($body)
            ->action('Открыть уведомления', 'view_notifications')
            ->action('Закрыть', 'close')
            ->data([
                'url' => route('notifications.index'),
                'test_type' => $this->testType,
                'test_data' => $this->testData,
                'timestamp' => now()->timestamp,
            ])
            ->options([
                'TTL' => 86400, // 24 часа
                'urgency' => 'normal',
                'topic' => 'test-notification',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'test_type' => $this->testType,
            'test_data' => $this->testData,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}
