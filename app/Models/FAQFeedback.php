<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FAQFeedback extends Model
{
    use HasFactory;

    protected $table = 'f_a_q_feedback';

    protected $fillable = [
        'faq_id',
        'user_id',
        'session_id',
        'feedback',
        'comment',
        'user_agent',
        'ip_address',
    ];

    /**
     * Типы фидбэка
     */
    public const FEEDBACK_HELPFUL = 'helpful';
    public const FEEDBACK_NOT_HELPFUL = 'not_helpful';

    /**
     * Получить FAQ
     */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class);
    }

    /**
     * Получить пользователя
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Проверить, полезен ли FAQ
     */
    public function isHelpful(): bool
    {
        return $this->feedback === self::FEEDBACK_HELPFUL;
    }

    /**
     * Проверить, не полезен ли FAQ
     */
    public function isNotHelpful(): bool
    {
        return $this->feedback === self::FEEDBACK_NOT_HELPFUL;
    }

    /**
     * Добавить фидбэк
     */
    public static function addFeedback(
        FAQ $faq,
        ?string $feedback,
        ?User $user = null,
        ?string $sessionId = null,
        ?string $comment = null,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): ?self {
        if (!$feedback || !in_array($feedback, [self::FEEDBACK_HELPFUL, self::FEEDBACK_NOT_HELPFUL])) {
            return null;
        }

        return self::updateOrCreate(
            [
                'faq_id' => $faq->id,
                'user_id' => $user?->id,
                'session_id' => $sessionId,
            ],
            [
                'feedback' => $feedback,
                'comment' => $comment,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ]
        );
    }

    /**
     * Получить статистику по фидбэку для FAQ
     */
    public static function getStatsForFaq(FAQ $faq): array
    {
        $total = self::where('faq_id', $faq->id)->count();
        $helpful = self::where('faq_id', $faq->id)
            ->where('feedback', self::FEEDBACK_HELPFUL)
            ->count();
        $notHelpful = self::where('faq_id', $faq->id)
            ->where('feedback', self::FEEDBACK_NOT_HELPFUL)
            ->count();

        return [
            'total' => $total,
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'helpful_percentage' => $total > 0 ? round(($helpful / $total) * 100, 2) : 0,
        ];
    }
}
