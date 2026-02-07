<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'review_id',
        'user_id',
        'reason',
        'description',
        'evidence',
        'status',
        'resolution',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'priority',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'evidence' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Проверка, ожидает ли жалоба рассмотрения
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Проверка, решена ли жалоба
    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'dismissed']);
    }

    // Начать расследование
    public function startInvestigation(): void
    {
        $this->status = 'investigating';
        $this->save();
    }

    // Решить жалобу
    public function resolve(
        string $resolution,
        User $resolver,
        string $notes = null
    ): void {
        $this->status = 'resolved';
        $this->resolution = $resolution;
        $this->resolution_notes = $notes;
        $this->resolved_by = $resolver->id;
        $this->resolved_at = now();
        $this->save();
    }

    // Отклонить жалобу
    public function dismiss(User $resolver, string $notes = null): void
    {
        $this->status = 'dismissed';
        $this->resolution = 'no_action';
        $this->resolution_notes = $notes;
        $this->resolved_by = $resolver->id;
        $this->resolved_at = now();
        $this->save();
    }

    // Отметить как дубликат
    public function markAsDuplicate(int $originalReportId): void
    {
        $this->status = 'duplicate';
        $this->resolution = 'no_action';

        $metadata = $this->metadata ?? [];
        $metadata['duplicate_of'] = $originalReportId;
        $this->metadata = $metadata;

        $this->save();
    }

    // Получить текстовое описание причины
    public function getReasonText(): string
    {
        return match($this->reason) {
            'spam' => 'Спам',
            'inappropriate' => 'Неподобающий контент',
            'false_information' => 'Ложная информация',
            'harassment' => 'Оскорбления/домогательства',
            'conflict_of_interest' => 'Конфликт интересов',
            'fake_review' => 'Поддельный отзыв',
            default => 'Другая причина',
        };
    }

    // Получить текстовое описание решения
    public function getResolutionText(): string
    {
        return match($this->resolution) {
            'review_hidden' => 'Отзыв скрыт',
            'review_edited' => 'Отзыв отредактирован',
            'user_warned' => 'Пользователь предупрежден',
            'user_banned' => 'Пользователь забанен',
            'no_action' => 'Действий не требуется',
            'pending' => 'Ожидает решения',
            default => 'Не указано',
        };
    }

    // Получить цвет статуса для отображения
    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'investigating' => 'info',
            'resolved' => 'success',
            'dismissed' => 'secondary',
            'duplicate' => 'light',
            default => 'dark',
        };
    }

    // Создать жалобу на отзыв
    public static function createReport(
        Review $review,
        User $user,
        string $reason,
        string $description = null,
        array $evidence = null
    ): self {
        return self::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'description' => $description,
            'evidence' => $evidence,
            'priority' => self::calculatePriority($reason, $review),
        ]);
    }

    // Рассчитать приоритет жалобы
    private static function calculatePriority(string $reason, Review $review): int
    {
        $priority = 1;

        // Повышаем приоритет для определенных причин
        if (in_array($reason, ['harassment', 'fake_review'])) {
            $priority = 3;
        }

        // Повышаем приоритет для отзывов с большим количеством просмотров
        if ($review->helpful_count + $review->unhelpful_count > 10) {
            $priority++;
        }

        // Проверяем, есть ли у пользователя другие жалобы
        $userReportCount = self::where('user_id', $review->user_id)
            ->where('status', 'pending')
            ->count();

        if ($userReportCount > 0) {
            $priority++;
        }

        return min($priority, 5); // Максимум 5
    }

    // Получить жалобы, требующие внимания
    public static function getPendingReports(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('status', 'pending')
            ->orWhere('status', 'investigating')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->with(['review', 'user', 'review.user'])
            ->get();
    }

    // Проверить, жаловался ли пользователь на этот отзыв
    public static function hasUserReported(Review $review, User $user): bool
    {
        return self::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'investigating'])
            ->exists();
    }

    // Получить статистику жалоб
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'pending' => self::where('status', 'pending')->count(),
            'investigating' => self::where('status', 'investigating')->count(),
            'resolved' => self::where('status', 'resolved')->count(),
            'by_reason' => self::groupBy('reason')
                ->selectRaw('reason, count(*) as count')
                ->pluck('count', 'reason')
                ->toArray(),
            'by_resolution' => self::whereNotNull('resolution')
                ->groupBy('resolution')
                ->selectRaw('resolution, count(*) as count')
                ->pluck('count', 'resolution')
                ->toArray(),
        ];
    }
}
