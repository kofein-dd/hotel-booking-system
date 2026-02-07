<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'user_id' => $this->user_id,
            'room_id' => $this->room_id,
            'hotel_id' => $this->hotel_id,

            // Даты
            'check_in' => $this->check_in->format('Y-m-d'),
            'check_out' => $this->check_out->format('Y-m-d'),
            'nights' => $this->nights,
            'guests_count' => $this->guests_count,

            // Цены
            'room_price_per_night' => (float) $this->room_price_per_night,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'extra_charges' => (float) $this->extra_charges,
            'total_price' => (float) $this->total_price,
            'currency' => $this->currency,

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'is_active' => $this->isActive(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'refund_amount' => (float) $this->refund_amount,

            // Гости
            'guest_info' => $this->guest_info,
            'additional_guests' => $this->additional_guests,

            // Отмена
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,

            // Дополнительно
            'special_requests' => $this->special_requests,
            'reminder_date' => $this->reminder_date?->format('Y-m-d'),

            // Даты
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'user' => new UserResource($this->whenLoaded('user')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'hotel' => new HotelResource($this->whenLoaded('hotel')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),

            // Расчет возврата
            'potential_refund' => $this->when($request->has('calculate_refund'), function() {
                return $this->calculateRefund();
            }),
        ];
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает подтверждения',
            'confirmed' => 'Подтверждено',
            'cancelled' => 'Отменено',
            'completed' => 'Завершено',
            'no_show' => 'Неявка',
            default => $this->status,
        };
    }
}
