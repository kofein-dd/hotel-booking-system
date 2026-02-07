<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'room_id',
        'check_in',
        'check_out',
        'guests_count',
        'total_price',
        'status',
        'cancellation_date',
        'cancellation_reason',
        'special_requests',
        'booking_source',
        'payment_status',
        'confirmation_number',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'cancellation_date' => 'datetime',
        'total_price' => 'decimal:2',
        'special_requests' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_NO_SHOW = 'no_show';
    const STATUS_REFUNDED = 'refunded';

    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_PARTIAL = 'partial';
    const PAYMENT_STATUS_REFUNDED = 'refunded';
    const PAYMENT_STATUS_FAILED = 'failed';

    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // Вычисляемые атрибуты
    public function getNightsAttribute()
    {
        return $this->check_in->diffInDays($this->check_out);
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function getCanCancelAttribute()
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        // Проверка отмены за месяц до заезда
        return now()->diffInDays($this->check_in, false) >= 30;
    }

    // Скоупы
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForDates($query, $checkIn, $checkOut)
    {
        return $query->where(function ($q) use ($checkIn, $checkOut) {
            $q->whereBetween('check_in', [$checkIn, $checkOut])
                ->orWhereBetween('check_out', [$checkIn, $checkOut])
                ->orWhere(function ($query) use ($checkIn, $checkOut) {
                    $query->where('check_in', '<=', $checkIn)
                        ->where('check_out', '>=', $checkOut);
                });
        });
    }
}
