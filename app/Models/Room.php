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
        'name',
        'slug',
        'description',
        'room_type_id',
        'price_per_night',
        'capacity',
        'max_occupancy',
        'size',
        'view',
        'amenities',
        'status',
        'is_featured',
        'sort_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $attributes = [
        'status' => 'available',
        'is_featured' => false,
        'sort_order' => 0,
        'capacity' => 2,
        'max_occupancy' => 2,
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'price_per_night' => 'decimal:2',
        'capacity' => 'integer',
        'max_occupancy' => 'integer',
        'size' => 'decimal:2',
        'sort_order' => 'integer',
        'amenities' => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'facility_room')
            ->withPivot('description', 'is_available')
            ->withTimestamps();
    }

    // Scopes:

    // Scope для получения доступных номеров
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // Алиас для обратной совместимости (если где-то используется active())
    public function scopeActive($query)
    {
        return $query->available();
    }

    // Scope для получения выделенных номеров
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Scope для сортировки
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }

    // Scope для получения номеров по цене
    public function scopeByPriceRange($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('price_per_night', [$minPrice, $maxPrice]);
    }

    // Scope для получения номеров по вместимости
    public function scopeByCapacity($query, $capacity)
    {
        return $query->where('capacity', '>=', $capacity);
    }

    // Scope для получения номеров по отелю
    public function scopeByHotel($query, $hotelId)
    {
        return $query->where('hotel_id', $hotelId);
    }
}
