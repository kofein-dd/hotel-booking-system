<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_number',
        'user_id',
        'room_id',
        'hotel_id',
        'check_in',
        'check_out',
        'nights',
        'guests_count',
        'room_price_per_night',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'extra_charges',
        'total_price',
        'currency',
        'status',
        'guest_info',
        'additional_guests',
        'cancelled_at',
        'cancellation_reason',
        'refund_amount',
        'special_requests',
        'metadata',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'guest_info' => 'array',
        'additional_guests' => 'array',
        'metadata' => 'array',
        'cancelled_at' => 'datetime',
        'room_price_per_night' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'extra_charges' => 'decimal:2',
        'total_price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    // Генерация номера брони
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (!$booking->booking_number) {
                $booking->booking_number = 'BOOK-' . strtoupper(uniqid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Проверка возможности отмены
    public function canBeCancelled(): bool
    {
        if ($this->status !== 'confirmed' && $this->status !== 'pending') {
            return false;
        }

        $daysBeforeCheckIn = now()->diffInDays($this->check_in, false);

        // Можно отменить за 30 дней до заезда
        return $daysBeforeCheckIn >= 30;
    }

    // Проверка актуальности брони
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
            $this->check_out > now();
    }

    // Получить дату напоминания (за 2 дня до заезда)
    public function getReminderDateAttribute()
    {
        return $this->check_in->subDays(2);
    }

    // Рассчитать сумму возврата при отмене
    public function calculateRefund($cancellationDate = null): float
    {
        $cancellationDate = $cancellationDate ?: now();
        $daysBeforeCheckIn = $cancellationDate->diffInDays($this->check_in, false);

        if ($daysBeforeCheckIn >= 30) {
            // Полный возврат за 30+ дней
            return $this->total_price;
        } elseif ($daysBeforeCheckIn >= 14) {
            // 50% возврат за 14-29 дней
            return $this->total_price * 0.5;
        } elseif ($daysBeforeCheckIn >= 7) {
            // 25% возврат за 7-13 дней
            return $this->total_price * 0.25;
        }

        // Менее 7 дней - возврата нет
        return 0;
    }
}
