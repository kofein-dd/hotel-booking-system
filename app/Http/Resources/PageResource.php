<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->url,

            // Контент
            'content' => $this->content,
            'excerpt' => $this->excerpt,

            // SEO
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,

            // Тип и статус
            'type' => $this->type,
            'status' => $this->status,
            'is_published' => $this->isPublished(),

            // Даты
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s'),

            // Медиа
            'featured_image' => $this->featured_image ? asset('storage/' . $this->featured_image) : null,
            'gallery' => $this->gallery ? array_map(fn($image) => asset('storage/' . $image), $this->gallery) : [],

            // Структура
            'parent_id' => $this->parent_id,
            'order' => $this->order,
            'full_path' => $this->getFullPath(),
            'breadcrumbs' => $this->getBreadcrumbs(),

            // Настройки
            'template' => $this->template,
            'is_indexable' => $this->is_indexable,
            'is_searchable' => $this->is_searchable,
            'show_in_menu' => $this->show_in_menu,
            'show_in_footer' => $this->show_in_footer,

            // Доступ
            'access_level' => $this->access_level,
            'is_accessible' => $this->isAccessible($request->user()),

            // Автор
            'author_id' => $this->author_id,

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'author' => new UserResource($this->whenLoaded('author')),
            'parent' => new PageResource($this->whenLoaded('parent')),
            'children' => PageResource::collection($this->whenLoaded('children')),
        ];
    }
}
