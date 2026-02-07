<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TestPushNotification extends Notification implements ShouldQueue
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
        return ['broadcast', 'database']; // Только для push и сохранения в БД
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

    public function toBroadcast(object $notifiable): BroadcastMessage
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

        $icon = match($this->testType) {
            'booking' => '🏨',
            'message' => '💬',
            'system' => '⚙️',
            'alert' => '🚨',
            default => '✅',
        };

        return new BroadcastMessage([
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'action_url' => route('notifications.index'),
            'test_type' => $this->testType,
            'test_data' => $this->testData,
            'timestamp' => now()->timestamp,
            'vibrate' => [200, 100, 200],
            'require_interaction' => $this->testType === 'alert',
            'sound' => $this->testType === 'alert' ? 'alert' : 'default',
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
