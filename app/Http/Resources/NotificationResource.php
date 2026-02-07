<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_number' => $this->notification_number,
            'user_id' => $this->user_id,
            'booking_id' => $this->booking_id,

            // Тип и категория
            'type' => $this->type,
            'category' => $this->category,
            'icon' => $this->icon,
            'color_class' => $this->color_class,

            // Содержание
            'subject' => $this->subject,
            'message' => $this->message,
            'short_message' => $this->short_message,
            'data' => $this->data,

            // Каналы отправки
            'via_site' => $this->via_site,
            'via_email' => $this->via_email,
            'via_sms' => $this->via_sms,
            'via_telegram' => $this->via_telegram,
            'via_push' => $this->via_push,

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'is_read' => $this->isRead(),
            'is_sent' => $this->isSent(),
            'is_pending' => $this->isPending(),

            // Даты
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s'),
            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),

            // Действие
            'has_action' => $this->hasAction(),
            'action_url' => $this->action_url,
            'action_text' => $this->action_text,

            // Флаги
            'is_important' => $this->is_important,
            'requires_action' => $this->requires_action,
            'is_broadcast' => $this->is_broadcast,

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'user' => new UserResource($this->whenLoaded('user')),
            'booking' => new BookingResource($this->whenLoaded('booking')),

            // Отчет о доставке (только для админов)
            'delivery_report' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->delivery_report
            ),
            'error_message' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->error_message
            ),
        ];
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает отправки',
            'sent' => 'Отправлено',
            'delivered' => 'Доставлено',
            'read' => 'Прочитано',
            'failed' => 'Ошибка отправки',
            'cancelled' => 'Отменено',
            default => $this->status,
        };
    }
}
