<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['sometimes', 'date', 'after_or_equal:today'],
            'check_out' => ['sometimes', 'date', 'after:check_in'],
            'guests_count' => ['sometimes', 'integer', 'min:1', 'max:10'],

            // Статус
            'status' => ['sometimes', 'string', Rule::in(['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])],

            // Отмена
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],

            // Обновление информации о гостях
            'guest_info' => ['nullable', 'array'],
            'guest_info.full_name' => ['sometimes', 'string', 'max:255'],
            'guest_info.email' => ['sometimes', 'email', 'max:255'],
            'guest_info.phone' => ['sometimes', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'additional_guests' => ['nullable', 'array'],
            'additional_guests.*.full_name' => ['required', 'string', 'max:255'],

            // Дополнительно
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.after_or_equal' => 'Дата заезда не может быть в прошлом',
            'guests_count.max' => 'Максимальное количество гостей: 10',
        ];
    }
}
