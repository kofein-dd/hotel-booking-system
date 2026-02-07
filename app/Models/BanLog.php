<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_id',
        'reason',
        'banned_until',
        'type',
        'is_permanent',
        'notes',
        'unbanned_at',
        'unban_reason',
    ];

    protected $casts = [
        'banned_until' => 'datetime',
        'unbanned_at' => 'datetime',
        'is_permanent' => 'boolean',
    ];

    const TYPE_FULL = 'full';
    const TYPE_BOOKING = 'booking';
    const TYPE_CHAT = 'chat';
    const TYPE_COMMENTS = 'comments';

    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // Скоупы
    public function scopeActive($query)
    {
        return $query->whereNull('unbanned_at')
            ->where(function ($q) {
                $q->where('is_permanent', true)
                    ->orWhere('banned_until', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereNull('unbanned_at')
            ->where('is_permanent', false)
            ->where('banned_until', '<=', now());
    }

    public function scopePermanent($query)
    {
        return $query->where('is_permanent', true)
            ->whereNull('unbanned_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Методы
    public function isActive()
    {
        if ($this->unbanned_at) {
            return false;
        }

        if ($this->is_permanent) {
            return true;
        }

        return $this->banned_until && $this->banned_until->isFuture();
    }

    public function unban($reason = null)
    {
        $this->update([
            'unbanned_at' => now(),
            'unban_reason' => $reason,
        ]);
    }

    public function getDurationAttribute()
    {
        if ($this->is_permanent) {
            return 'Навсегда';
        }

        if (!$this->banned_until) {
            return 'Не указано';
        }

        return $this->created_at->diffForHumans($this->banned_until, true);
    }
}
