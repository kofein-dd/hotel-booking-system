<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'photos',
        'videos',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'contact_info',
        'amenities',
        'social_links',
        'status',
        'non_working_days',
        'settings',
    ];

    protected $casts = [
        'photos' => 'array',
        'videos' => 'array',
        'contact_info' => 'array',
        'amenities' => 'array',
        'social_links' => 'array',
        'non_working_days' => 'array',
        'settings' => 'array',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function activeRooms()
    {
        return $this->rooms()->where('status', 'available');
    }

    public function reviews()
    {
        return $this->hasManyThrough(Review::class, Room::class);
    }

    public function bookings()
    {
        return $this->hasManyThrough(Booking::class, Room::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnNonWorkingDay(\Carbon\Carbon $date): bool
    {
        if (!$this->non_working_days) {
            return false;
        }

        return in_array($date->toDateString(), $this->non_working_days);
    }

    public function getMainPhotoAttribute()
    {
        $photos = $this->photos;
        return $photos ? $photos[0] : null;
    }
}
