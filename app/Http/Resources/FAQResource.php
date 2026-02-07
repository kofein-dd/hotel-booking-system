<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FAQResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'short_answer' => $this->short_answer,

            // Категория
            'category' => $this->category,
            'category_order' => $this->category_order,

            // Приоритет и видимость
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'show_on_homepage' => $this->show_on_homepage,
            'is_published' => $this->isPublished(),

            // Статистика
            'views' => $this->views,
            'helpful_count' => $this->helpful_count,
            'unhelpful_count' => $this->unhelpful_count,
            'helpfulness_rating' => $this->getHelpfulnessRating(),

            // Теги
            'tags' => $this->tags,

            // Автор
            'author_id' => $this->author_id,
            'last_edited_by' => $this->last_edited_by,

            // Даты
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'last_edited_at' => $this->last_edited_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Связи
            'related_faq_id' => $this->related_faq_id,

            // Отношения
            'author' => new UserResource($this->whenLoaded('author')),
            'last_editor' => new UserResource($this->whenLoaded('lastEditor')),
            'related_faq' => new FAQResource($this->whenLoaded('relatedFaq')),
            'related_faqs' => FAQResource::collection($this->whenLoaded('relatedFaqs')),

            // Дополнительные данные
            'related_items' => $this->when($request->has('include_related'),
                fn() => $this->getRelated()
            ),

            // Проверка полезности от текущего пользователя
            'user_feedback' => $this->when($request->user(), function() use ($request) {
                // Здесь можно добавить логику для проверки, отметил ли пользователь FAQ как полезный
                return null;
            }),
        ];
    }
}
