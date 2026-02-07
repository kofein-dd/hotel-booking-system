<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'amount',
        'method',
        'status',
        'transaction_id',
        'payment_date',
        'payment_details',
        'currency',
        'refund_amount',
        'refund_date',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'refund_date' => 'datetime',
        'refund_amount' => 'decimal:2',
        'payment_details' => 'array',
    ];

    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_DEBIT_CARD = 'debit_card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CASH = 'cash';
    const METHOD_ONLINE = 'online';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIAL_REFUND = 'partial_refund';

    const CURRENCY_RUB = 'RUB';
    const CURRENCY_USD = 'USD';
    const CURRENCY_EUR = 'EUR';

    // Отношения
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Скоупы
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    // Методы
    public function markAsCompleted($transactionId = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'payment_date' => now(),
            'transaction_id' => $transactionId ?? $this->transaction_id,
        ]);
    }

    public function refund($amount = null, $reason = null)
    {
        $refundAmount = $amount ?? $this->amount;

        $this->update([
            'status' => $refundAmount < $this->amount ? self::STATUS_PARTIAL_REFUND : self::STATUS_REFUNDED,
            'refund_amount' => $refundAmount,
            'refund_date' => now(),
            'refund_reason' => $reason,
        ]);
    }
}
