<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'user_id',
        'payment_number',
        'amount',
        'amount_received',
        'currency',
        'method',
        'status',
        'transaction_id',
        'gateway_response_id',
        'gateway_response',
        'payment_date',
        'refund_date',
        'due_date',
        'refund_amount',
        'refund_reason',
        'refund_transaction_id',
        'payment_details',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'refund_date' => 'datetime',
        'due_date' => 'date',
        'gateway_response' => 'array',
        'payment_details' => 'array',
        'metadata' => 'array',
    ];

    // Генерация номера платежа
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->payment_number) {
                $payment->payment_number = 'PAY-' . strtoupper(uniqid());
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Проверка, является ли платеж успешным
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['completed', 'processing']);
    }

    // Проверка, является ли платеж ожидающим
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Проверка, был ли платеж возвращен
    public function isRefunded(): bool
    {
        return in_array($this->status, ['refunded', 'partially_refunded']);
    }

    // Получить сумму, доступную для возврата
    public function getAvailableForRefund(): float
    {
        if (!$this->isSuccessful()) {
            return 0;
        }

        $refunded = $this->refund_amount ?? 0;
        return $this->amount_received - $refunded;
    }

    // Создать возврат
    public function createRefund(float $amount, string $reason = null): bool
    {
        if ($amount > $this->getAvailableForRefund()) {
            return false;
        }

        $this->refund_amount = ($this->refund_amount ?? 0) + $amount;
        $this->refund_reason = $reason;
        $this->refund_date = now();

        if ($this->refund_amount >= $this->amount_received) {
            $this->status = 'refunded';
        } else {
            $this->status = 'partially_refunded';
        }

        return $this->save();
    }

    // Обновить статус платежа
    public function updateStatus(string $status, array $gatewayData = null): void
    {
        $this->status = $status;

        if ($gatewayData) {
            $this->gateway_response = $gatewayData;
            $this->gateway_response_id = $gatewayData['id'] ?? null;
            $this->transaction_id = $gatewayData['transaction_id'] ?? $this->transaction_id;

            if ($status === 'completed' && !$this->payment_date) {
                $this->payment_date = now();
                $this->amount_received = $gatewayData['amount_received'] ?? $this->amount;
            }
        }

        $this->save();
    }

    // Получить детали платежа для отображения
    public function getPaymentDetailsForDisplay(): array
    {
        $details = $this->payment_details ?? [];

        switch ($this->method) {
            case 'credit_card':
            case 'debit_card':
                return [
                    'method' => 'Карта',
                    'details' => $details['card_last4'] ?? '****',
                    'icon' => 'credit-card'
                ];
            case 'paypal':
                return [
                    'method' => 'PayPal',
                    'details' => $details['email'] ?? '',
                    'icon' => 'paypal'
                ];
            case 'bank_transfer':
                return [
                    'method' => 'Банковский перевод',
                    'details' => $details['account'] ?? '',
                    'icon' => 'bank'
                ];
            default:
                return [
                    'method' => ucfirst(str_replace('_', ' ', $this->method)),
                    'details' => '',
                    'icon' => 'payment'
                ];
        }
    }
}
