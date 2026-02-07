<?php

namespace App\Http\Requests\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $discountId = $this->route('discount') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Z0-9-_]+$/',
                Rule::unique('discounts')->ignore($discountId)
            ],

            // Тип и значение
            'value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],

            // Условия
            'minimum_booking_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_nights' => ['nullable', 'integer', 'min:1'],
            'maximum_nights' => ['nullable', 'integer', 'min:1'],
            'maximum_guests' => ['nullable', 'integer', 'min:1'],

            // Даты действия
            'valid_from' => ['nullable', 'date'],
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
            'code.unique' => 'Такой промокод уже существует',
            'valid_to.after' => 'Дата окончания должна быть позже даты начала',
        ];
    }
}
