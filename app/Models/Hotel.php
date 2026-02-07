<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'photos',
        'videos',
        'coordinates',
        'contact_info',
        'status',
        'working_days',
        'check_in_time',
        'check_out_time',
        'rules',
        'amenities',
        'social_links',
    ];

    protected $casts = [
        'photos' => 'array',
        'videos' => 'array',
        'coordinates' => 'array',
        'contact_info' => 'array',
        'working_days' => 'array',
        'rules' => 'array',
        'amenities' => 'array',
        'social_links' => 'array',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';

    // Отношения
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function bookings()
    {
        return $this->hasManyThrough(Booking::class, Room::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Скоупы
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereHas('rooms', function ($q) {
                $q->where('status', Room::STATUS_AVAILABLE);
            });
    }
}
