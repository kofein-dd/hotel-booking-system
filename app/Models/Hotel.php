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
        'slug',
        'description',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'website',
        'stars',
        'check_in_time',
        'check_out_time',
        'amenities',
        'policies',
        'status',
        'is_featured',
        'sort_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $attributes = [
        'address' => 'Не указан',
        'city' => 'Не указан',
        'country' => 'Россия',
        'phone' => 'Не указан',
        'stars' => 3,
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
        'status' => 'active',
        'is_featured' => false,
        'sort_order' => 0,
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'stars' => 'integer',
        'amenities' => 'array',
        'sort_order' => 'integer',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Отношение к изображениям отеля.
     */
    public function images()
    {
        return $this->hasMany(HotelImage::class)->ordered();
    }

    /**
     * Получить главное изображение.
     */
    public function getMainImageAttribute()
    {
        return $this->images()->main()->first() ?? $this->images()->first();
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'facility_hotel')
            ->withPivot('description', 'is_available')
            ->withTimestamps();
    }

    // Добавляем недостающие scopes:

    // Scope для получения активных отелей
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope для получения выделенных отелей
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Scope для сортировки
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Scope для поиска по городу
    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    // Scope для поиска по стране
    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    // Scope для получения отелей с определенным количеством звезд
    public function scopeByStars($query, $stars)
    {
        return $query->where('stars', '>=', $stars);
    }
}
