<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'hotel_id' => ['required', 'exists:hotels,id'],

            // Даты
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'nights' => ['required', 'integer', 'min:1', 'max:365'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:10'],

            // Цены
            'room_price_per_night' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'subtotal' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'extra_charges' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'total_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'currency' => ['required', 'string', 'size:3'],

            // Статус
            'status' => ['required', 'string', Rule::in(['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])],

            // Гости
            'guest_info' => ['required', 'array'],
            'guest_info.full_name' => ['required', 'string', 'max:255'],
            'guest_info.email' => ['required', 'email', 'max:255'],
            'guest_info.phone' => ['required', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'guest_info.passport' => ['nullable', 'string', 'max:100'],
            'additional_guests' => ['nullable', 'array'],
            'additional_guests.*.full_name' => ['required', 'string', 'max:255'],
            'additional_guests.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],

            // Дополнительно
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'discount_code' => ['nullable', 'string', 'exists:discounts,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.after_or_equal' => 'Дата заезда не может быть в прошлом',
            'check_out.after' => 'Дата выезда должна быть позже даты заезда',
            'guests_count.max' => 'Максимальное количество гостей: 10',
            'nights.max' => 'Максимальная продолжительность бронирования: 365 дней',
            'guest_info.phone.regex' => 'Неверный формат телефона',
            'additional_guests.*.age.max' => 'Некорректный возраст',
            'discount_code.exists' => 'Неверный промокод',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Пользователь',
            'room_id' => 'Номер',
            'hotel_id' => 'Отель',
            'check_in' => 'Дата заезда',
            'check_out' => 'Дата выезда',
            'nights' => 'Количество ночей',
            'guests_count' => 'Количество гостей',
            'room_price_per_night' => 'Цена за ночь',
            'total_price' => 'Общая стоимость',
            'guest_info.full_name' => 'Имя гостя',
            'guest_info.email' => 'Email гостя',
            'guest_info.phone' => 'Телефон гостя',
            'discount_code' => 'Промокод',
        ];
    }

    public function prepareForValidation()
    {
        // Автоматически рассчитываем количество ночей
        if ($this->has(['check_in', 'check_out'])) {
            $checkIn = \Carbon\Carbon::parse($this->check_in);
            $checkOut = \Carbon\Carbon::parse($this->check_out);
            $this->merge([
                'nights' => $checkIn->diffInDays($checkOut),
            ]);
        }
    }
}
