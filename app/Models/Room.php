<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'name',
        'type',
        'slug',
        'description',
        'capacity',
        'price_per_night',
        'total_rooms',
        'available_rooms',
        'amenities',
        'photos',
        'size',
        'bed_types',
        'view',
        'extra_services',
        'status',
        'order',
        'settings',
    ];

    protected $casts = [
        'amenities' => 'array',
        'photos' => 'array',
        'bed_types' => 'array',
        'view' => 'array',
        'extra_services' => 'array',
        'settings' => 'array',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && $this->available_rooms > 0;
    }

    public function getMainPhotoAttribute()
    {
        $photos = $this->photos;
        return $photos ? $photos[0] : null;
    }

    public function getPriceForDates($nights, $guests = null)
    {
        $price = $this->price_per_night * $nights;

        // Доп. логика расчета (например, доплата за гостей)
        if ($guests && $guests > $this->capacity) {
            // Можно добавить логику доплаты
        }

        return $price;
    }

    public function updateAvailability()
    {
        $activeBookings = $this->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_out', '>', now())
            ->count();

        $this->available_rooms = max(0, $this->total_rooms - $activeBookings);
        $this->save();
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'facility_hotel')
            ->withPivot('description', 'is_available')
            ->withTimestamps();
    }
}
