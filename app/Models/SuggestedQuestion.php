<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestedQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question',
        'description',
        'email',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
        'faq_id',
        'votes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'votes' => 'integer',
    ];

    /**
     * Статусы вопроса
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ADDED_TO_FAQ = 'added_to_faq';

    /**
     * Получить пользователя, предложившего вопрос
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить администратора, который рассмотрел вопрос
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Получить связанный FAQ (если вопрос был добавлен)
     */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class);
    }

    /**
     * Проверить, ожидает ли вопрос рассмотрения
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Проверить, одобрен ли вопрос
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Проверить, добавлен ли вопрос в FAQ
     */
    public function isAddedToFaq(): bool
    {
        return $this->status === self::STATUS_ADDED_TO_FAQ;
    }

    /**
     * Обновить статус вопроса
     */
    public function updateStatus(string $status, ?User $reviewer = null, ?string $comment = null): void
    {
        $this->update([
            'status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);
    }

    /**
     * Увеличить счетчик голосов
     */
    public function incrementVotes(): void
    {
        $this->increment('votes');
    }

    /**
     * Получить вопросы, ожидающие рассмотрения
     */
    public static function getPending(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('status', self::STATUS_PENDING)
            ->orderBy('votes', 'desc')
            ->orderBy('created_at')
            ->get();
    }
}
