<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'notification_number',
        'user_id',
        'booking_id',
        'type',
        'category',
        'subject',
        'message',
        'data',
        'via_site',
        'via_email',
        'via_sms',
        'via_telegram',
        'via_push',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'delivery_report',
        'error_message',
        'is_important',
        'requires_action',
        'is_broadcast',
        'action_url',
        'action_text',
        'metadata',
    ];

    protected $casts = [
        'data' => 'array',
        'delivery_report' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'via_site' => 'boolean',
        'via_email' => 'boolean',
        'via_sms' => 'boolean',
        'via_telegram' => 'boolean',
        'via_push' => 'boolean',
        'is_important' => 'boolean',
        'requires_action' => 'boolean',
        'is_broadcast' => 'boolean',
    ];

    // Генерация номера уведомления
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($notification) {
            if (!$notification->notification_number) {
                $notification->notification_number = 'NOTIF-' . strtoupper(uniqid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // Проверка, прочитано ли уведомление
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    // Проверка, отправлено ли уведомление
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    // Проверка, ожидает ли отправки
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Проверка, можно ли отправлять сейчас
    public function shouldBeSentNow(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if ($this->scheduled_at && $this->scheduled_at->isFuture()) {
            return false;
        }

        return true;
    }

    // Отметить как прочитанное
    public function markAsRead(): bool
    {
        if (!$this->read_at) {
            $this->read_at = now();
            $this->status = 'read';
            return $this->save();
        }

        return false;
    }

    // Отметить как отправленное
    public function markAsSent(array $report = null): bool
    {
        if ($this->status === 'pending') {
            $this->status = 'sent';
            $this->sent_at = now();

            if ($report) {
                $this->delivery_report = $report;
            }

            return $this->save();
        }

        return false;
    }

    // Отметить как доставленное
    public function markAsDelivered(): bool
    {
        if ($this->status === 'sent') {
            $this->status = 'delivered';
            $this->delivered_at = now();
            return $this->save();
        }

        return false;
    }

    // Отметить как неудачное
    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        return $this->save();
    }

    // Получить иконку для типа уведомления
    public function getIconAttribute(): string
    {
        return match($this->type) {
            'booking' => 'calendar-check',
            'payment' => 'credit-card',
            'reminder' => 'bell',
            'promotion' => 'tag',
            'support' => 'headset',
            'review' => 'star',
            'admin' => 'shield',
            default => 'info-circle',
        };
    }

    // Получить цвет для категории
    public function getColorClassAttribute(): string
    {
        return match($this->category) {
            'success' => 'success',
            'warning' => 'warning',
            'error' => 'danger',
            'important' => 'danger',
            default => 'info',
        };
    }

    // Получить короткое сообщение (для превью)
    public function getShortMessageAttribute(): string
    {
        return strlen($this->message) > 100
            ? substr($this->message, 0, 100) . '...'
            : $this->message;
    }

    // Проверка, имеет ли уведомление действие
    public function hasAction(): bool
    {
        return !empty($this->action_url) && !empty($this->action_text);
    }

    // Создать уведомление о напоминании о брони
    public static function createBookingReminder(Booking $booking, int $daysBefore = 2): self
    {
        return self::create([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'type' => 'reminder',
            'category' => 'info',
            'subject' => "Напоминание о бронировании #{$booking->booking_number}",
            'message' => "До вашего заезда в отель осталось {$daysBefore} дня. Дата заезда: {$booking->check_in->format('d.m.Y')}.",
            'via_site' => true,
            'via_email' => true,
            'via_push' => true,
            'scheduled_at' => $booking->check_in->subDays($daysBefore),
            'action_url' => route('booking.show', $booking),
            'action_text' => 'Посмотреть бронь',
            'data' => [
                'booking_number' => $booking->booking_number,
                'check_in' => $booking->check_in->toDateString(),
                'days_before' => $daysBefore,
            ],
        ]);
    }

    // Создать массовое уведомление для всех пользователей
    public static function createBroadcastNotification(
        string $subject,
        string $message,
        array $channels = ['site']
    ): self {
        return self::create([
            'type' => 'admin',
            'category' => 'important',
            'subject' => $subject,
            'message' => $message,
            'via_site' => in_array('site', $channels),
            'via_email' => in_array('email', $channels),
            'via_push' => in_array('push', $channels),
            'is_broadcast' => true,
            'is_important' => true,
            'status' => 'pending',
        ]);
    }
}
