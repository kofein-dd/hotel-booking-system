<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'type',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Отношение с отелями
    public function hotels()
    {
        return $this->belongsToMany(Hotel::class, 'facility_hotel')
            ->withPivot('description', 'is_available')
            ->withTimestamps();
    }

    // Отношение с номерами
    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'facility_room')
            ->withPivot('description', 'is_available')
            ->withTimestamps();
    }

    // Scopes:

    // Scope для активных удобств
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope для удобств определенного типа
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Scope для сортировки
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Scope для поиска по названию
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%')
            ->orWhere('description', 'like', '%' . $search . '%');
    }
}
