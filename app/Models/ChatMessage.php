<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_id',
        'booking_id',
        'message',
        'attachment',
        'is_admin_message',
        'read_at',
        'message_type',
    ];

    protected $casts = [
        'is_admin_message' => 'boolean',
        'read_at' => 'datetime',
        'attachment' => 'array',
    ];

    const TYPE_TEXT = 'text';
    const TYPE_IMAGE = 'image';
    const TYPE_FILE = 'file';
    const TYPE_SYSTEM = 'system';

    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Скоупы
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Методы
    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function isUnread()
    {
        return is_null($this->read_at);
    }

    public function senderName()
    {
        if ($this->is_admin_message && $this->admin) {
            return $this->admin->name . ' (Администратор)';
        }

        return $this->user->name ?? 'Пользователь';
    }
}
