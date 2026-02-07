<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FAQ extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'question',
        'answer',
        'short_answer',
        'category',
        'category_order',
        'priority',
        'is_active',
        'is_featured',
        'show_on_homepage',
        'views',
        'helpful_count',
        'unhelpful_count',
        'tags',
        'author_id',
        'last_edited_by',
        'published_at',
        'last_edited_at',
        'related_faq_id',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'show_on_homepage' => 'boolean',
        'views' => 'integer',
        'helpful_count' => 'integer',
        'unhelpful_count' => 'integer',
        'priority' => 'integer',
        'category_order' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function relatedFaq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class, 'related_faq_id');
    }

    public function relatedFaqs(): HasMany
    {
        return $this->hasMany(FAQ::class, 'related_faq_id');
    }

    // Проверка, опубликован ли FAQ
    public function isPublished(): bool
    {
        return $this->is_active &&
            (!$this->published_at || $this->published_at <= now());
    }

    // Увеличить счетчик просмотров
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    // Отметить как полезный
    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    // Отметить как не полезный
    public function markUnhelpful(): void
    {
        $this->increment('unhelpful_count');
    }

    // Получить рейтинг полезности
    public function getHelpfulnessRating(): float
    {
        $total = $this->helpful_count + $this->unhelpful_count;

        if ($total === 0) {
            return 0;
        }

        return round(($this->helpful_count / $total) * 100, 1);
    }

    // Получить все категории
    public static function getCategories(): array
    {
        return self::where('is_active', true)
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    // Получить FAQ по категориям
    public static function getByCategories(): array
    {
        return self::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('category')
            ->orderBy('category_order')
            ->orderBy('priority', 'desc')
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    // Получить избранные FAQ
    public static function getFeatured(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where('is_featured', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();
    }

    // Получить FAQ для главной страницы
    public static function getForHomepage(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where('show_on_homepage', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('category_order')
            ->limit(6)
            ->get();
    }

    // Поиск FAQ
    public static function search(string $query): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('question', 'like', "%{$query}%")
                    ->orWhere('answer', 'like', "%{$query}%")
                    ->orWhere('short_answer', 'like', "%{$query}%")
                    ->orWhereJsonContains('tags', $query);
            })
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('views', 'desc')
            ->get();
    }

    // Обновить информацию о редактировании
    public function updateEditInfo(User $editor): void
    {
        $this->last_edited_by = $editor->id;
        $this->last_edited_at = now();
        $this->save();
    }

    // Получить связанные FAQ
    public function getRelated(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('id', '!=', $this->id)
            ->where('category', $this->category)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('views', 'desc')
            ->limit(5)
            ->get();
    }
}
