<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paymentDetails = $this->getPaymentDetailsForDisplay();

        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,

            // Суммы
            'amount' => (float) $this->amount,
            'amount_received' => (float) $this->amount_received,
            'currency' => $this->currency,

            // Метод оплаты
            'method' => $this->method,
            'method_display' => $paymentDetails['method'],
            'method_icon' => $paymentDetails['icon'],
            'payment_details' => $paymentDetails['details'],

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'is_successful' => $this->isSuccessful(),
            'is_pending' => $this->isPending(),
            'is_refunded' => $this->isRefunded(),

            // Идентификаторы
            'transaction_id' => $this->transaction_id,
            'gateway_response_id' => $this->gateway_response_id,

            // Даты
            'payment_date' => $this->payment_date?->format('Y-m-d H:i:s'),
            'refund_date' => $this->refund_date?->format('Y-m-d H:i:s'),
            'due_date' => $this->due_date?->format('Y-m-d'),

            // Возврат
            'refund_amount' => (float) $this->refund_amount,
            'refund_reason' => $this->refund_reason,
            'refund_transaction_id' => $this->refund_transaction_id,
            'available_for_refund' => (float) $this->getAvailableForRefund(),

            // Описание
            'description' => $this->description,

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'user' => new UserResource($this->whenLoaded('user')),

            // Дополнительные данные
            'gateway_response' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->gateway_response
            ),
        ];
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает оплаты',
            'processing' => 'В обработке',
            'completed' => 'Успешно завершен',
            'failed' => 'Неудача',
            'refunded' => 'Возвращен',
            'partially_refunded' => 'Частично возвращен',
            'cancelled' => 'Отменен',
            default => $this->status,
        };
    }
}
