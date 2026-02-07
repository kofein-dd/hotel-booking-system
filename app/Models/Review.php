<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'booking_id',
        'room_id',
        'hotel_id',
        'rating_overall',
        'rating_cleanliness',
        'rating_comfort',
        'rating_location',
        'rating_service',
        'rating_value',
        'title',
        'comment',
        'pros',
        'cons',
        'photos',
        'videos',
        'status',
        'hotel_reply',
        'hotel_reply_at',
        'hotel_reply_by',
        'helpful_count',
        'unhelpful_count',
        'metadata',
    ];

    protected $casts = [
        'photos' => 'array',
        'videos' => 'array',
        'hotel_reply_at' => 'datetime',
        'metadata' => 'array',
        'rating_overall' => 'integer',
        'rating_cleanliness' => 'integer',
        'rating_comfort' => 'integer',
        'rating_location' => 'integer',
        'rating_service' => 'integer',
        'rating_value' => 'integer',
    ];

    // Валидация рейтингов при сохранении
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($review) {
            // Ограничиваем рейтинг от 1 до 5
            $ratingFields = [
                'rating_overall',
                'rating_cleanliness',
                'rating_comfort',
                'rating_location',
                'rating_service',
                'rating_value',
            ];

            foreach ($ratingFields as $field) {
                if (!is_null($review->$field)) {
                    $review->$field = max(1, min(5, $review->$field));
                }
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function replyAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hotel_reply_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    // Проверка, одобрен ли отзыв
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    // Проверка, находится ли на модерации
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Проверка, можно ли редактировать отзыв (в течение 24 часов после создания)
    public function canBeEdited(): bool
    {
        return $this->created_at->diffInHours(now()) <= 24 && $this->isPending();
    }

    // Расчет общего рейтинга из категорий
    public function calculateOverallRating(): float
    {
        $ratings = [
            $this->rating_cleanliness,
            $this->rating_comfort,
            $this->rating_location,
            $this->rating_service,
            $this->rating_value,
        ];

        $validRatings = array_filter($ratings, function($rating) {
            return !is_null($rating);
        });

        if (empty($validRatings)) {
            return $this->rating_overall;
        }

        return round(array_sum($validRatings) / count($validRatings), 1);
    }

    // Получить оценку в виде звезд (HTML)
    public function getStarsHtml(): string
    {
        $rating = $this->rating_overall;
        $stars = '';

        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<i class="fas fa-star text-warning"></i>';
            } elseif ($i - 0.5 <= $rating) {
                $stars .= '<i class="fas fa-star-half-alt text-warning"></i>';
            } else {
                $stars .= '<i class="far fa-star text-warning"></i>';
            }
        }

        return $stars;
    }

    // Добавить голос "полезно"
    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    // Добавить голос "не полезно"
    public function markUnhelpful(): void
    {
        $this->increment('unhelpful_count');
    }

    // Добавить ответ от отеля
    public function addHotelReply(string $reply, User $user): void
    {
        $this->hotel_reply = $reply;
        $this->hotel_reply_at = now();
        $this->hotel_reply_by = $user->id;
        $this->save();
    }

    // Получить средний рейтинг по всем категориям
    public function getAverageRatings(): array
    {
        return [
            'overall' => $this->rating_overall,
            'cleanliness' => $this->rating_cleanliness,
            'comfort' => $this->rating_comfort,
            'location' => $this->rating_location,
            'service' => $this->rating_service,
            'value' => $this->rating_value,
        ];
    }
}
