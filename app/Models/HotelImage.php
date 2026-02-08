<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'url',
        'alt_text',
        'title',
        'sort_order',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Отношение к отелю.
     */
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Scope для главного изображения.
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Scope для сортировки.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Getter для полного URL изображения.
     */
    public function getFullUrlAttribute()
    {
        if (str_starts_with($this->url, 'http')) {
            return $this->url;
        }

        return asset('storage/' . ltrim($this->url, '/'));
    }

    /**
     * Getter для миниатюры.
     */
    public function getThumbnailUrlAttribute()
    {
        $url = $this->full_url;

        // Здесь можно добавить логику для миниатюр
        // Например, если используется Cloudinary или Intervention Image
        return $url;
    }
}
