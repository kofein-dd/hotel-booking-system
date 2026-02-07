<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'user_id' => ['required', 'exists:users,id'],

            // Суммы
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'currency' => ['required', 'string', 'size:3'],

            // Метод оплаты
            'method' => ['required', 'string', Rule::in([
                'credit_card', 'debit_card', 'bank_transfer', 'paypal', 'stripe', 'yookassa', 'cash', 'other'
            ])],

            // Статус
            'status' => ['required', 'string', Rule::in([
                'pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded', 'cancelled'
            ])],

            // Идентификаторы
            'transaction_id' => ['nullable', 'string', 'max:255', 'unique:payments'],

            // Даты
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],

            // Детали платежа
            'payment_details' => ['nullable', 'array'],
            'description' => ['nullable', 'string', 'max:1000'],

            // Для кредитных карт
            'payment_details.card_number' => ['nullable', 'string', 'regex:/^\d{16}$/'],
            'payment_details.card_holder' => ['nullable', 'string', 'max:255'],
            'payment_details.expiry_date' => ['nullable', 'string', 'regex:/^\d{2}\/\d{2}$/'],
            'payment_details.cvv' => ['nullable', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Сумма платежа должна быть положительной',
            'transaction_id.unique' => 'Такой идентификатор транзакции уже используется',
            'payment_details.card_number.regex' => 'Номер карты должен содержать 16 цифр',
            'payment_details.expiry_date.regex' => 'Неверный формат даты истечения (ММ/ГГ)',
            'payment_details.cvv.regex' => 'Неверный формат CVV (3 или 4 цифры)',
            'payment_date.before_or_equal' => 'Дата платежа не может быть в будущем',
        ];
    }

    public function attributes(): array
    {
        return [
            'booking_id' => 'Бронирование',
            'amount' => 'Сумма',
            'method' => 'Метод оплаты',
            'transaction_id' => 'Идентификатор транзакции',
            'payment_date' => 'Дата платежа',
            'due_date' => 'Срок оплаты',
        ];
    }
}
