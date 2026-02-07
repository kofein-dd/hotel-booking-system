<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'room_number',
        'type',
        'name',
        'description',
        'capacity',
        'price_per_night',
        'photos',
        'videos',
        'amenities',
        'status',
        'size',
        'bed_type',
        'view',
        'floor',
        'extra_beds',
        'extra_bed_price',
        'max_occupancy',
    ];

    protected $casts = [
        'photos' => 'array',
        'videos' => 'array',
        'amenities' => 'array',
        'price_per_night' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
    ];

    const TYPE_STANDARD = 'standard';
    const TYPE_SUPERIOR = 'superior';
    const TYPE_DELUXE = 'deluxe';
    const TYPE_SUITE = 'suite';
    const TYPE_PRESIDENTIAL = 'presidential';

    const STATUS_AVAILABLE = 'available';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_RESERVED = 'reserved';

    const BED_TYPE_SINGLE = 'single';
    const BED_TYPE_DOUBLE = 'double';
    const BED_TYPE_TWIN = 'twin';
    const BED_TYPE_QUEEN = 'queen';
    const BED_TYPE_KING = 'king';

    // Отношения
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Методы проверки доступности
    public function isAvailableForDates($checkIn, $checkOut)
    {
        return !$this->bookings()
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in', '<=', $checkIn)
                            ->where('check_out', '>=', $checkOut);
                    });
            })
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])
            ->exists();
    }

    public function getPriceForDates($nights, $guests = 1)
    {
        $basePrice = $this->price_per_night * $nights;

        // Доплата за дополнительных гостей (если больше capacity)
        if ($guests > $this->capacity) {
            $extraGuests = $guests - $this->capacity;
            // TODO: Добавить логику расчета доплат
        }

        return $basePrice;
    }
}
