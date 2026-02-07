<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'options',
        'order',
        'is_public',
        'is_required',
        'validation_rules',
        'metadata',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'metadata' => 'array',
        'is_public' => 'boolean',
        'is_required' => 'boolean',
        'order' => 'integer',
    ];

    // Получить значение с учетом типа
    public function getValueAttribute($value)
    {
        return match($this->type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => (bool) $value,
            'json', 'array' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    // Установить значение с учетом типа
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = match($this->type) {
            'json', 'array' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    // Получить настройку по ключу
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        if ($setting) {
            return $setting->value;
        }

        return $default;
    }

    // Установить настройку
    public static function setValue(string $key, $value, string $type = 'string', string $group = 'general'): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    // Получить все настройки группы
    public static function getGroup(string $group): array
    {
        return self::where('group', $group)
            ->orderBy('order')
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    // Проверка существования настройки
    public static function has(string $key): bool
    {
        return self::where('key', $key)->exists();
    }

    // Удалить настройку
    public static function remove(string $key): bool
    {
        return self::where('key', $key)->delete();
    }

    // Получить все публичные настройки
    public static function getPublicSettings(): array
    {
        return self::where('is_public', true)
            ->orderBy('group')
            ->orderBy('order')
            ->get()
            ->groupBy('group')
            ->map(function ($items) {
                return $items->pluck('value', 'key')->toArray();
            })
            ->toArray();
    }
}
