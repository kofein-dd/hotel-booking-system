<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
        'notification_preferences',
        'email_notifications',
        'push_notifications',
        'sms_notifications',
        'email_verified_at',
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
        'notification_preferences' => 'array',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned' ||
            ($this->banned_until && $this->banned_until->isFuture());
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function bans()
    {
        return $this->hasMany(BanList::class, 'user_id');
    }

    /**
     * Получить push-подписки пользователя
     */
    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Получить настройки уведомлений
     */
    public function getNotificationPreferencesAttribute($value)
    {
        $defaults = [
            'bookings_email' => true,
            'bookings_push' => true,
            'payments_email' => true,
            'payments_push' => true,
            'reviews_email' => true,
            'chat_messages_email' => true,
            'chat_messages_push' => true,
            'promotions_email' => false,
            'newsletter_email' => false,
        ];

        if (!$value) {
            return $defaults;
        }

        $preferences = json_decode($value, true);
        return array_merge($defaults, $preferences);
    }

    /**
     * Установить настройки уведомлений
     */
    public function setNotificationPreferencesAttribute($value)
    {
        $this->attributes['notification_preferences'] = json_encode($value);
    }

    /**
     * Проверить, подтвержден ли email
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Отметить email как подтвержденный
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Отправить email для подтверждения
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
    }

    /**
     * Получить email для подтверждения
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }
}
