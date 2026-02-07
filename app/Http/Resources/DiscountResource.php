<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,

            // Тип и значение
            'type' => $this->type,
            'type_display' => $this->getTypeDisplay(),
            'value' => (float) $this->value,
            'currency' => $this->currency,

            // Применимость
            'applicable_to' => $this->applicable_to,
            'applicable_to_display' => $this->getApplicableToDisplay(),
            'applicable_values' => $this->applicable_values,

            // Условия
            'minimum_booking_amount' => (float) $this->minimum_booking_amount,
            'minimum_nights' => $this->minimum_nights,
            'maximum_nights' => $this->maximum_nights,
            'maximum_guests' => $this->maximum_guests,

            // Даты действия
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'is_active' => $this->isActive(),

            // Лимиты использования
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'used_count' => $this->used_count,
            'remaining_uses' => $this->usage_limit ? $this->usage_limit - $this->used_count : null,

            // Статус
            'is_public' => $this->is_public,
            'is_auto_apply' => $this->is_auto_apply,
            'priority' => $this->priority,

            // Описание
            'description' => $this->description,
            'terms' => $this->terms,
            'display_description' => $this->getDisplayDescription(),

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'users_used' => UserResource::collection($this->whenLoaded('usersUsed')),

            // Доступность для текущего пользователя
            'is_available_for_current_user' => $this->when($request->user(), function() use ($request) {
                return $this->isAvailableForUser($request->user());
            }),

            // Проверка условий
            'conditions_met' => $this->when($request->has('check_conditions'), function() use ($request) {
                $context = [
                    'booking_amount' => $request->get('booking_amount'),
                    'nights' => $request->get('nights'),
                    'guests' => $request->get('guests'),
                    'room_type' => $request->get('room_type'),
                    'room_id' => $request->get('room_id'),
                ];
                return $this->checkConditions(array_filter($context));
            }),

            // Расчет скидки
            'calculated_discount' => $this->when($request->has('calculate_for_amount'), function() use ($request) {
                $amount = (float) $request->get('calculate_for_amount');
                $context = [
                    'nights' => $request->get('nights'),
                    'guests' => $request->get('guests'),
                    'room_type' => $request->get('room_type'),
                    'room_id' => $request->get('room_id'),
                    'night_price' => $request->get('night_price'),
                ];
                return $this->applyToAmount($amount, array_filter($context));
            }),
        ];
    }

    private function getTypeDisplay(): string
    {
        return match($this->type) {
            'percentage' => 'Процентная скидка',
            'fixed' => 'Фиксированная сумма',
            'free_night' => 'Бесплатная ночь',
            'upgrade' => 'Улучшение номера',
            default => $this->type,
        };
    }

    private function getApplicableToDisplay(): string
    {
        return match($this->applicable_to) {
            'all' => 'На все',
            'room_type' => 'На тип номера',
            'specific_room' => 'На конкретный номер',
            'booking_duration' => 'На продолжительность брони',
            'seasonal' => 'Сезонная',
            'first_booking' => 'Первое бронирование',
            default => $this->applicable_to,
        };
    }
}
