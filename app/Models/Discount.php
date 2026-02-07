<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'currency',
        'applicable_to',
        'applicable_values',
        'minimum_booking_amount',
        'minimum_nights',
        'maximum_nights',
        'maximum_guests',
        'valid_from',
        'valid_to',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'is_active',
        'is_public',
        'is_auto_apply',
        'priority',
        'description',
        'terms',
        'metadata',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_booking_amount' => 'decimal:2',
        'applicable_values' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_auto_apply' => 'boolean',
        'metadata' => 'array',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'discount_id');
    }

    public function usersUsed(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discount_user')
            ->withPivot(['used_at', 'booking_id'])
            ->withTimestamps();
    }

    // Проверка активности скидки
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $now->gt($this->valid_to)) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    // Проверка доступности для пользователя
    public function isAvailableForUser(?User $user = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (!$user) {
            return true;
        }

        // Проверка лимита на пользователя
        if ($this->usage_limit_per_user) {
            $userUsageCount = $this->usersUsed()->where('user_id', $user->id)->count();
            if ($userUsageCount >= $this->usage_limit_per_user) {
                return false;
            }
        }

        // Проверка для первой брони
        if ($this->applicable_to === 'first_booking' && $user->bookings()->count() > 0) {
            return false;
        }

        return true;
    }

    // Применить скидку к сумме
    public function applyToAmount(float $amount, array $context = []): array
    {
        if (!$this->isActive()) {
            return [
                'discount_amount' => 0,
                'final_amount' => $amount,
                'applied' => false,
                'message' => 'Скидка не активна',
            ];
        }

        // Проверка минимальной суммы
        if ($this->minimum_booking_amount && $amount < $this->minimum_booking_amount) {
            return [
                'discount_amount' => 0,
                'final_amount' => $amount,
                'applied' => false,
                'message' => 'Минимальная сумма брони не достигнута',
            ];
        }

        // Проверка условий
        if (!$this->checkConditions($context)) {
            return [
                'discount_amount' => 0,
                'final_amount' => $amount,
                'applied' => false,
                'message' => 'Условия скидки не выполнены',
            ];
        }

        // Расчет суммы скидки
        $discountAmount = 0;

        switch ($this->type) {
            case 'percentage':
                $discountAmount = ($amount * $this->value) / 100;
                break;
            case 'fixed':
                $discountAmount = min($this->value, $amount);
                break;
            case 'free_night':
                // Логика для бесплатной ночи
                $nightPrice = $context['night_price'] ?? ($amount / ($context['nights'] ?? 1));
                $discountAmount = min($nightPrice, $amount);
                break;
            case 'upgrade':
                // Логика для апгрейда (специальная обработка)
                $discountAmount = 0; // Специальный тип, требует отдельной логики
                break;
        }

        $finalAmount = max(0, $amount - $discountAmount);

        return [
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'applied' => true,
            'message' => 'Скидка применена',
            'discount_name' => $this->name,
            'discount_type' => $this->type,
        ];
    }

    // Проверка условий применения
    private function checkConditions(array $context): bool
    {
        // Проверка количества ночей
        if ($this->minimum_nights && ($context['nights'] ?? 0) < $this->minimum_nights) {
            return false;
        }

        if ($this->maximum_nights && ($context['nights'] ?? 0) > $this->maximum_nights) {
            return false;
        }

        // Проверка количества гостей
        if ($this->maximum_guests && ($context['guests'] ?? 0) > $this->maximum_guests) {
            return false;
        }

        // Проверка конкретных условий
        switch ($this->applicable_to) {
            case 'room_type':
                if (!isset($context['room_type']) || !in_array($context['room_type'], $this->applicable_values ?? [])) {
                    return false;
                }
                break;
            case 'specific_room':
                if (!isset($context['room_id']) || !in_array($context['room_id'], $this->applicable_values ?? [])) {
                    return false;
                }
                break;
            case 'booking_duration':
                if (!isset($context['nights']) || !$this->checkDurationCondition($context['nights'])) {
                    return false;
                }
                break;
            case 'seasonal':
                if (!$this->checkSeasonalCondition()) {
                    return false;
                }
                break;
        }

        return true;
    }

    // Проверка условия по продолжительности
    private function checkDurationCondition(int $nights): bool
    {
        $ranges = $this->applicable_values ?? [];

        foreach ($ranges as $range) {
            if (isset($range['min']) && $nights < $range['min']) continue;
            if (isset($range['max']) && $nights > $range['max']) continue;
            return true;
        }

        return false;
    }

    // Проверка сезонного условия
    private function checkSeasonalCondition(): bool
    {
        $now = Carbon::now();
        $seasons = $this->applicable_values ?? [];

        foreach ($seasons as $season) {
            $start = Carbon::parse($season['start'] ?? '');
            $end = Carbon::parse($season['end'] ?? '');

            if ($now->between($start, $end)) {
                return true;
            }
        }

        return false;
    }

    // Увеличить счетчик использований
    public function incrementUsage(?User $user = null, ?Booking $booking = null): void
    {
        $this->increment('used_count');

        if ($user) {
            $this->usersUsed()->attach($user->id, [
                'used_at' => now(),
                'booking_id' => $booking?->id,
            ]);
        }
    }

    // Получить доступные скидки для пользователя
    public static function getAvailableForUser(?User $user = null, array $context = []): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where('is_public', true)
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->where(function ($query) use ($context) {
                if (isset($context['booking_amount'])) {
                    $query->whereNull('minimum_booking_amount')
                        ->orWhere('minimum_booking_amount', '<=', $context['booking_amount']);
                }
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function ($discount) use ($user, $context) {
                return $discount->isAvailableForUser($user) &&
                    $discount->checkConditions($context);
            });
    }

    // Проверить промокод
    public static function validateCode(string $code, ?User $user = null, array $context = []): ?array
    {
        $discount = self::where('code', $code)->first();

        if (!$discount) {
            return null;
        }

        if (!$discount->isAvailableForUser($user)) {
            return null;
        }

        $application = $discount->applyToAmount($context['amount'] ?? 0, $context);

        if (!$application['applied']) {
            return null;
        }

        return [
            'discount' => $discount,
            'application' => $application,
        ];
    }

    // Получить описание скидки для отображения
    public function getDisplayDescription(): string
    {
        $description = $this->name . ': ';

        switch ($this->type) {
            case 'percentage':
                $description .= "{$this->value}% скидка";
                break;
            case 'fixed':
                $description .= "{$this->value} {$this->currency} скидка";
                break;
            case 'free_night':
                $description .= "1 бесплатная ночь";
                break;
            case 'upgrade':
                $description .= "Улучшение номера";
                break;
        }

        if ($this->minimum_booking_amount) {
            $description .= " при заказе от {$this->minimum_booking_amount} {$this->currency}";
        }

        if ($this->valid_to) {
            $description .= " (действует до " . $this->valid_to->format('d.m.Y') . ")";
        }

        return $description;
    }
}
