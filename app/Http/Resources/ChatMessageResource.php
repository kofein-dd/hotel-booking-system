<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'admin_id' => $this->admin_id,
            'booking_id' => $this->booking_id,

            // Сообщение
            'message' => $this->message,
            'message_type' => $this->message_type,
            'is_admin_message' => $this->is_admin_message,

            // Отправитель
            'sender_name' => $this->getSenderName(),
            'sender_avatar' => $this->getSenderAvatar() ? asset('storage/' . $this->getSenderAvatar()) : null,

            // Вложения
            'has_attachments' => $this->hasAttachments(),
            'attachments' => $this->when($this->hasAttachments(), fn() => $this->getAttachmentsList()),

            // Статус
            'is_read' => $this->isRead(),
            'is_delivered' => $this->isDelivered(),
            'is_system_message' => $this->isSystemMessage(),
            'can_be_deleted' => $this->canBeDeleted(),

            // Даты
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at,

            // Удаление
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'deleted_by' => $this->deleted_by,

            // Отношения
            'user' => new UserResource($this->whenLoaded('user')),
            'admin' => new UserResource($this->whenLoaded('admin')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'deleted_by_user' => new UserResource($this->whenLoaded('deletedBy')),

            // Форматирование для фронтенда
            'is_current_user' => $this->when($request->user(), function() use ($request) {
                if ($this->is_admin_message) {
                    return $request->user()->id === $this->admin_id;
                }
                return $request->user()->id === $this->user_id;
            }),
        ];
    }
}
