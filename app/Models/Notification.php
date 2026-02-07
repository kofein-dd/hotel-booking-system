<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
        'scheduled_at',
        'sent_at',
        'channel',
        'priority',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'data' => 'array',
    ];

    const TYPE_BOOKING_CONFIRMATION = 'booking_confirmation';
    const TYPE_BOOKING_CANCELLATION = 'booking_cancellation';
    const TYPE_PAYMENT_SUCCESS = 'payment_success';
    const TYPE_PAYMENT_FAILED = 'payment_failed';
    const TYPE_REMINDER = 'reminder';
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_CHAT_MESSAGE = 'chat_message';
    const TYPE_SYSTEM = 'system';

    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_TELEGRAM = 'telegram';
    const CHANNEL_IN_APP = 'in_app';

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')->whereNull('sent_at');
    }

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    public function scopeForChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    // Методы
    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsSent()
    {
        $this->update(['sent_at' => now()]);
    }

    public function isRead()
    {
        return !is_null($this->read_at);
    }

    public function isScheduled()
    {
        return !is_null($this->scheduled_at) && is_null($this->sent_at);
    }

    public function shouldSend()
    {
        if (!$this->scheduled_at) {
            return true;
        }

        return now()->gte($this->scheduled_at) && is_null($this->sent_at);
    }
}
