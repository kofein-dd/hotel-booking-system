<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewChatMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChatMessage $message,
        public ?User $sender = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Отправляем email только если настройка включена
        if ($notifiable->notification_preferences['chat_messages_email'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $senderName = $this->sender ? $this->sender->name : 'Администратор';
        $isAdminMessage = $this->message->is_admin_message;

        return (new MailMessage)
            ->subject($isAdminMessage ? '📨 Новое сообщение от администратора' : '📨 Новое сообщение в чате')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("У вас новое сообщение от **{$senderName}**:")
            ->line('')
            ->line('**Сообщение:**')
            ->line($this->message->message)
            ->line('')
            ->action('Перейти к чату', route('chat.index'))
            ->line('Если вы не ожидали это сообщение, пожалуйста, свяжитесь с поддержкой.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_chat_message',
            'chat_message_id' => $this->message->id,
            'sender_id' => $this->sender?->id,
            'sender_name' => $this->sender?->name,
            'is_admin_message' => $this->message->is_admin_message,
            'message_preview' => \Illuminate\Support\Str::limit($this->message->message, 100),
            'action_url' => route('chat.show', $this->message->chat_session_id ?? $this->message->id),
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $senderName = $this->sender ? $this->sender->name : 'Администратор';
        $preview = \Illuminate\Support\Str::limit($this->message->message, 50);

        return new BroadcastMessage([
            'title' => $this->message->is_admin_message ? 'Сообщение от администратора' : 'Новое сообщение',
            'body' => "{$senderName}: {$preview}",
            'icon' => '💬',
            'action_url' => route('chat.show', $this->message->chat_session_id ?? $this->message->id),
            'chat_message_id' => $this->message->id,
            'sender_id' => $this->sender?->id,
            'is_admin_message' => $this->message->is_admin_message,
            'sound' => 'chat',
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'chat_message_id' => $this->message->id,
            'sender_id' => $this->sender?->id,
            'message_preview' => \Illuminate\Support\Str::limit($this->message->message, 100),
            'is_admin_message' => $this->message->is_admin_message,
        ];
    }
}
