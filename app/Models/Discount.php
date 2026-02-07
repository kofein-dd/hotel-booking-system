<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_amount',
        'max_discount',
        'valid_from',
        'valid_to',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'is_active',
        'room_types',
        'booking_days',
        'excluded_dates',
        'conditions',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'is_active' => 'boolean',
        'room_types' => 'array',
        'booking_days' => 'array',
        'excluded_dates' => 'array',
        'conditions' => 'array',
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
    ];

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';
    const TYPE_FREE_NIGHT = 'free_night';

    // Отношения
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'discount_code', 'code');
    }

    // Скоупы
    public function scopeActive($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_to', '>=', $now)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereRaw('usage_count < usage_limit');
            });
    }

    public function scopeValidFor($query, $roomType = null, $bookingDate = null)
    {
        $query->active();

        if ($roomType) {
            $query->where(function ($q) use ($roomType) {
                $q->whereNull('room_types')
                    ->orWhereJsonContains('room_types', $roomType);
            });
        }

        if ($bookingDate) {
            $query->where(function ($q) use ($bookingDate) {
                $q->whereNull('booking_days')
                    ->orWhereJsonContains('booking_days', $bookingDate->format('l'));
            });
        }

        return $query;
    }

    // Методы
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $now->gt($this->valid_to)) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function calculateDiscount($amount)
    {
        if (!$this->isValid() || $amount < $this->min_amount) {
            return 0;
        }

        $discount = 0;

        switch ($this->type) {
            case self::TYPE_PERCENTAGE:
                $discount = $amount * ($this->value / 100);
                break;
            case self::TYPE_FIXED:
                $discount = $this->value;
                break;
            case self::TYPE_FREE_NIGHT:
                // Логика для бесплатных ночей будет в BookingService
                $discount = 0;
                break;
        }

        // Ограничение максимальной скидки
        if ($this->max_discount && $discount > $this->max_discount) {
            $discount = $this->max_discount;
        }

        return min($discount, $amount);
    }

    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    public function canUse(User $user = null)
    {
        if ($user && $this->per_user_limit) {
            $userUsage = Booking::where('user_id', $user->id)
                ->where('discount_code', $this->code)
                ->count();

            if ($userUsage >= $this->per_user_limit) {
                return false;
            }
        }

        return $this->isValid();
    }
}
