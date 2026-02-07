<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'type',
        'status',
        'published_at',
        'scheduled_at',
        'featured_image',
        'gallery',
        'template',
        'parent_id',
        'order',
        'is_indexable',
        'is_searchable',
        'show_in_menu',
        'show_in_footer',
        'access_level',
        'author_id',
        'metadata',
    ];

    protected $casts = [
        'meta_keywords' => 'array',
        'gallery' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'is_indexable' => 'boolean',
        'is_searchable' => 'boolean',
        'show_in_menu' => 'boolean',
        'show_in_footer' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }

    // Проверка, опубликована ли страница
    public function isPublished(): bool
    {
        return $this->status === 'published' &&
            (!$this->published_at || $this->published_at <= now());
    }

    // Проверка, доступна ли страница для просмотра
    public function isAccessible(?User $user = null): bool
    {
        if (!$this->isPublished()) {
            return false;
        }

        return match($this->access_level) {
            'public' => true,
            'registered' => $user !== null,
            'private' => $user && ($user->isAdmin() || $user->id === $this->author_id),
            default => false,
        };
    }

    // Получить полный путь (включая родителей)
    public function getFullPath(): string
    {
        $path = $this->slug;
        $parent = $this->parent;

        while ($parent) {
            $path = $parent->slug . '/' . $path;
            $parent = $parent->parent;
        }

        return $path;
    }

    // Получить URL страницы
    public function getUrlAttribute(): string
    {
        if ($this->type === 'home') {
            return url('/');
        }

        return url('pages/' . $this->getFullPath());
    }

    // Получить хлебные крошки
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $page = $this;

        while ($page) {
            $breadcrumbs[] = [
                'title' => $page->title,
                'url' => $page->url,
            ];
            $page = $page->parent;
        }

        return array_reverse($breadcrumbs);
    }

    // Публиковать страницу
    public function publish(): bool
    {
        $this->status = 'published';
        $this->published_at = now();
        return $this->save();
    }

    // Запланировать публикацию
    public function schedule(Carbon $date): bool
    {
        $this->status = 'scheduled';
        $this->scheduled_at = $date;
        return $this->save();
    }

    // Получить опубликованные страницы для меню
    public static function getMenuPages(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('status', 'published')
            ->where('show_in_menu', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where('access_level', 'public')
            ->with('children')
            ->orderBy('order')
            ->orderBy('title')
            ->get();
    }

    // Получить страницу по slug
    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->first();
    }

    // Генерация slug из заголовка
    public function generateSlug(): string
    {
        $slug = \Illuminate\Support\Str::slug($this->title);
        $count = self::where('slug', 'like', $slug . '%')->count();

        return $count > 0 ? $slug . '-' . ($count + 1) : $slug;
    }

    // Автоматическая генерация slug перед созданием
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = $page->generateSlug();
            }
        });
    }
}
