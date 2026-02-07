<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $sender;
    public $recipientId;

    /**
     * Создать новый экземпляр события
     */
    public function __construct(ChatMessage $message, ?User $sender = null, int $recipientId)
    {
        $this->message = $message;
        $this->sender = $sender;
        $this->recipientId = $recipientId;

        // Загружаем отношения для трансляции
        $this->message->load(['sender', 'recipient']);
    }

    /**
     * Получить каналы, на которые должно транслироваться событие
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->recipientId}"),
            new PrivateChannel("admin.chat"),
        ];
    }

    /**
     * Имя события для трансляции
     */
    public function broadcastAs(): string
    {
        return 'chat.message.new';
    }

    /**
     * Данные для трансляции
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
                'avatar' => $this->sender?->avatar_url,
            ],
            'recipient_id' => $this->message->recipient_id,
            'is_admin_message' => $this->message->is_admin_message,
            'created_at' => $this->message->created_at->toISOString(),
            'updated_at' => $this->message->updated_at->toISOString(),
            'read_at' => $this->message->read_at?->toISOString(),
        ];
    }

    /**
     * Условия для трансляции события
     */
    public function broadcastWhen(): bool
    {
        return true; // Всегда транслируем
    }
}
