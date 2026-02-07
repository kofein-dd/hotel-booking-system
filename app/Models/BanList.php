<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class BanList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'banned_by',
        'type',
        'reason',
        'reason_description',
        'evidence',
        'banned_at',
        'banned_until',
        'auto_unban',
        'unbanned_at',
        'unbanned_by',
        'restrict_booking',
        'restrict_messaging',
        'restrict_reviews',
        'restrictions',
        'is_active',
        'warning_count',
        'ban_history',
        'ip_address',
        'user_agent',
        'technical_data',
        'metadata',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'banned_until' => 'datetime',
        'unbanned_at' => 'datetime',
        'evidence' => 'array',
        'restrictions' => 'array',
        'ban_history' => 'array',
        'technical_data' => 'array',
        'metadata' => 'array',
        'restrict_booking' => 'boolean',
        'restrict_messaging' => 'boolean',
        'restrict_reviews' => 'boolean',
        'auto_unban' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function unbannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unbanned_by');
    }

    // Проверка активности бана
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->type === 'permanent') {
            return true;
        }

        if ($this->type === 'temporary' && $this->banned_until) {
            return now()->lt($this->banned_until);
        }

        return true;
    }

    // Проверка истек ли срок бана
    public function isExpired(): bool
    {
        if ($this->type === 'permanent') {
            return false;
        }

        if ($this->banned_until && now()->gte($this->banned_until)) {
            return true;
        }

        return false;
    }

    // Проверка является ли бан постоянным
    public function isPermanent(): bool
    {
        return $this->type === 'permanent';
    }

    // Проверка является ли бан временным
    public function isTemporary(): bool
    {
        return $this->type === 'temporary';
    }

    // Получить оставшееся время бана
    public function getRemainingTime(): ?string
    {
        if (!$this->isActive() || $this->isPermanent() || !$this->banned_until) {
            return null;
        }

        $now = now();
        if ($now->gte($this->banned_until)) {
            return 'истек';
        }

        $diff = $now->diff($this->banned_until);

        if ($diff->days > 0) {
            return $diff->days . ' ' . $this->pluralize($diff->days, 'день', 'дня', 'дней');
        } elseif ($diff->h > 0) {
            return $diff->h . ' ' . $this->pluralize($diff->h, 'час', 'часа', 'часов');
        } else {
            return $diff->i . ' ' . $this->pluralize($diff->i, 'минута', 'минуты', 'минут');
        }
    }

    // Вспомогательный метод для склонения
    private function pluralize(int $number, string $one, string $two, string $many): string
    {
        $number = abs($number) % 100;
        if ($number > 10 && $number < 20) {
            return $many;
        }

        $number %= 10;
        if ($number === 1) {
            return $one;
        }

        if ($number >= 2 && $number <= 4) {
            return $two;
        }

        return $many;
    }

    // Разбанить пользователя
    public function unban(User $unbannedBy, string $reason = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $this->is_active = false;
        $this->unbanned_at = now();
        $this->unbanned_by = $unbannedBy->id;

        // Добавить в историю
        $history = $this->ban_history ?? [];
        $history[] = [
            'action' => 'unban',
            'by' => $unbannedBy->id,
            'at' => now()->toDateTimeString(),
            'reason' => $reason,
        ];

        $this->ban_history = $history;

        // Обновить пользователя
        $this->user->status = 'active';
        $this->user->banned_until = null;
        $this->user->save();

        return $this->save();
    }

    // Автоматический разбан по истечении срока
    public function autoUnbanIfNeeded(): bool
    {
        if (!$this->auto_unban || !$this->is_active || !$this->isExpired()) {
            return false;
        }

        $this->is_active = false;
        $this->unbanned_at = now();

        // Добавить в историю
        $history = $this->ban_history ?? [];
        $history[] = [
            'action' => 'auto_unban',
            'at' => now()->toDateTimeString(),
        ];

        $this->ban_history = $history;

        // Обновить пользователя
        $this->user->status = 'active';
        $this->user->banned_until = null;
        $this->user->save();

        return $this->save();
    }

    // Продлить бан
    public function extendBan(Carbon $until, User $extendedBy, string $reason = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $oldUntil = $this->banned_until;
        $this->banned_until = $until;

        // Добавить в историю
        $history = $this->ban_history ?? [];
        $history[] = [
            'action' => 'extend',
            'by' => $extendedBy->id,
            'at' => now()->toDateTimeString(),
            'old_until' => $oldUntil?->toDateTimeString(),
            'new_until' => $until->toDateTimeString(),
            'reason' => $reason,
        ];

        $this->ban_history = $history;

        // Обновить пользователя
        $this->user->banned_until = $until;
        $this->user->save();

        return $this->save();
    }

    // Добавить предупреждение
    public function addWarning(User $warnedBy, string $reason): bool
    {
        $this->warning_count++;

        // Добавить в историю
        $history = $this->ban_history ?? [];
        $history[] = [
            'action' => 'warning',
            'by' => $warnedBy->id,
            'at' => now()->toDateTimeString(),
            'reason' => $reason,
            'warning_number' => $this->warning_count,
        ];

        $this->ban_history = $history;

        return $this->save();
    }

    // Проверить, заблокирован ли пользователь для бронирования
    public function isBookingRestricted(): bool
    {
        return $this->isActive() && $this->restrict_booking;
    }

    // Проверить, заблокирован ли пользователь для отправки сообщений
    public function isMessagingRestricted(): bool
    {
        return $this->isActive() && $this->restrict_messaging;
    }

    // Проверить, заблокирован ли пользователь для отзывов
    public function isReviewRestricted(): bool
    {
        return $this->isActive() && $this->restrict_reviews;
    }

    // Получить описание бана для отображения
    public function getDisplayDescription(): string
    {
        $description = "Бан: " . $this->getReasonText();

        if ($this->isPermanent()) {
            $description .= " (Постоянный)";
        } elseif ($this->banned_until) {
            $description .= " до " . $this->banned_until->format('d.m.Y H:i');

            if ($remaining = $this->getRemainingTime()) {
                $description .= " (осталось: $remaining)";
            }
        }

        return $description;
    }

    // Получить текстовое описание причины
    public function getReasonText(): string
    {
        return match($this->reason) {
            'spam' => 'Спам',
            'abuse' => 'Оскорбительное поведение',
            'fraud' => 'Мошенничество',
            'multiple_accounts' => 'Множественные аккаунты',
            'chargeback' => 'Возврат платежа',
            'policy_violation' => 'Нарушение правил',
            'inappropriate_content' => 'Неподобающий контент',
            default => $this->reason_description ?? 'Другая причина',
        };
    }

    // Создать новый бан
    public static function createBan(
        User $user,
        User $bannedBy,
        string $type = 'temporary',
        ?Carbon $bannedUntil = null,
        string $reason = 'other',
        array $restrictions = ['booking' => true]
    ): self {
        // Деактивировать старые активные баны
        self::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Создать новый бан
        $ban = self::create([
            'user_id' => $user->id,
            'banned_by' => $bannedBy->id,
            'type' => $type,
            'reason' => $reason,
            'banned_until' => $bannedUntil,
            'restrict_booking' => $restrictions['booking'] ?? true,
            'restrict_messaging' => $restrictions['messaging'] ?? false,
            'restrict_reviews' => $restrictions['reviews'] ?? false,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Обновить статус пользователя
        $user->status = 'banned';
        $user->banned_until = $bannedUntil;
        $user->save();

        return $ban;
    }

    // Проверить, забанен ли пользователь
    public static function isUserBanned(int $userId): bool
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->exists();
    }

    // Получить активный бан пользователя
    public static function getUserActiveBan(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }
}
