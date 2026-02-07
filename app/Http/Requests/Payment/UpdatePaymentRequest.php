<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentId = $this->route('payment') ?? $this->route('id');

        return [
            'status' => ['sometimes', 'string', Rule::in([
                'pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded', 'cancelled'
            ])],

            // Идентификаторы
            'transaction_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('payments')->ignore($paymentId)
            ],

            // Даты
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'refund_date' => ['nullable', 'date', 'before_or_equal:today'],

            // Возврат
            'refund_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'refund_reason' => ['nullable', 'string', 'max:500'],
            'refund_transaction_id' => ['nullable', 'string', 'max:255'],

            // Детали
            'gateway_response' => ['nullable', 'array'],
            'amount_received' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_id.unique' => 'Такой идентификатор транзакции уже используется',
            'payment_date.before_or_equal' => 'Дата платежа не может быть в будущем',
            'refund_date.before_or_equal' => 'Дата возврата не может быть в будущем',
            'refund_amount.max' => 'Сумма возврата не может превышать сумму платежа',
        ];
    }
}
