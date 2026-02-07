<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'old_status',
        'new_status',
        'changed_by',
        'change_reason',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Получить бронирование
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Получить пользователя, изменившего статус
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Создать запись в истории статусов
     */
    public static function createHistory(
        Booking $booking,
        string $newStatus,
        ?string $oldStatus = null,
        ?User $changedBy = null,
        ?string $reason = null,
        ?array $additionalData = null
    ): self {
        return self::create([
            'booking_id' => $booking->id,
            'old_status' => $oldStatus ?? $booking->status,
            'new_status' => $newStatus,
            'changed_by' => $changedBy?->id,
            'change_reason' => $reason,
            'additional_data' => $additionalData,
        ]);
    }

    /**
     * Получить историю изменений для бронирования
     */
    public static function getForBooking(Booking $booking): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('booking_id', $booking->id)
            ->with('changedByUser')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Проверить, изменился ли статус
     */
    public function isStatusChanged(): bool
    {
        return $this->old_status !== $this->new_status;
    }
}
