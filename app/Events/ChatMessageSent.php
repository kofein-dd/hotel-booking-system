<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $roomId;

    /**
     * Создать новый экземпляр события
     */
    public function __construct(ChatMessage $message, string $roomId)
    {
        $this->message = $message;
        $this->roomId = $roomId;

        $this->message->load(['user', 'admin']);
    }

    /**
     * Получить каналы, на которые должно транслироваться событие
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("chat.room.{$this->roomId}"),
        ];
    }

    /**
     * Имя события для трансляции (Socket.io style)
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * Данные для трансляции в формате Socket.io
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->message,
                'sender' => [
                    'id' => $this->message->user_id ?? $this->message->admin_id,
                    'name' => $this->message->user?->name ?? $this->message->admin?->name ?? 'Администратор',
                    'type' => $this->message->is_admin_message ? 'admin' : 'user',
                    'avatar' => $this->message->user?->avatar_url ?? $this->message->admin?->avatar_url,
                ],
                'timestamp' => $this->message->created_at->timestamp,
                'time' => $this->message->created_at->format('H:i'),
                'date' => $this->message->created_at->format('d.m.Y'),
                'is_read' => !is_null($this->message->read_at),
                'is_admin' => $this->message->is_admin_message,
            ],
            'room' => $this->roomId,
            'event_time' => now()->toISOString(),
        ];
    }

    /**
     * Условия для трансляции
     */
    public function broadcastWhen(): bool
    {
        return !empty($this->message->message);
    }

    /**
     * Имя очереди для обработки события
     */
    public function broadcastQueue(): string
    {
        return 'chat';
    }
}
