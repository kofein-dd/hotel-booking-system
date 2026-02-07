<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
        'banned_until',
        'avatar',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'banned_until' => 'datetime',
        'preferences' => 'array',
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_MODERATOR = 'moderator';
    const ROLE_USER = 'user';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_BANNED = 'banned';

    // Отношения
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function banLogs()
    {
        return $this->hasMany(BanLog::class);
    }

    // Методы проверки ролей
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator()
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function isUser()
    {
        return $this->role === self::ROLE_USER;
    }

    public function isBanned()
    {
        return $this->status === self::STATUS_BANNED ||
            ($this->banned_until && $this->banned_until->isFuture());
    }

    public function hasRole($role): bool
    {
        return $this->role === $role;
    }
}
