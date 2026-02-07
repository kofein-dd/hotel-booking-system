<?php

namespace App\Http\Requests\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:discounts', 'regex:/^[A-Z0-9-_]+$/'],

            // Тип и значение
            'type' => ['required', 'string', Rule::in(['percentage', 'fixed', 'free_night', 'upgrade'])],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],

            // Применимость
            'applicable_to' => ['required', 'string', Rule::in([
                'all', 'room_type', 'specific_room', 'booking_duration', 'seasonal', 'first_booking'
            ])],
            'applicable_values' => ['nullable', 'array'],

            // Условия
            'minimum_booking_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_nights' => ['nullable', 'integer', 'min:1'],
            'maximum_nights' => ['nullable', 'integer', 'min:1', 'gt:minimum_nights'],
            'maximum_guests' => ['nullable', 'integer', 'min:1'],

            // Даты действия
            'valid_from' => ['nullable', 'date', 'after_or_equal:today'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],

            // Лимиты использования
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],

            // Статус
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'is_auto_apply' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Описание
            'description' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Код может содержать только заглавные латинские буквы, цифры, дефисы и подчеркивания',
            'code.unique' => 'Такой промокод уже существует',
            'valid_from.after_or_equal' => 'Дата начала действия не может быть в прошлом',
            'valid_to.after' => 'Дата окончания должна быть позже даты начала',
            'maximum_nights.gt' => 'Максимальное количество ночей должно быть больше минимального',
            'value.min' => 'Значение скидки должно быть положительным',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Название скидки',
            'code' => 'Промокод',
            'type' => 'Тип скидки',
            'value' => 'Значение',
            'valid_from' => 'Дата начала',
            'valid_to' => 'Дата окончания',
            'usage_limit' => 'Лимит использований',
            'minimum_booking_amount' => 'Минимальная сумма брони',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_public' => $this->boolean('is_public', true),
            'is_auto_apply' => $this->boolean('is_auto_apply', false),
            'currency' => $this->input('currency', 'USD'),
            'priority' => $this->input('priority', 0),
        ]);
    }
}
