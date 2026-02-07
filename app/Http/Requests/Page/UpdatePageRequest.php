<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pageId = $this->route('page') ?? $this->route('id');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages')->ignore($pageId)
            ],
            'content' => ['nullable', 'string', 'max:50000'],
            'excerpt' => ['nullable', 'string', 'max:500'],

            // SEO
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'array'],

            // Тип и статус
            'status' => ['sometimes', 'string', Rule::in(['published', 'draft', 'scheduled', 'private', 'archived'])],

            // Даты
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],

            // Медиа
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],

            // Структура
            'parent_id' => ['nullable', 'exists:pages,id'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],

            // Настройки
            'is_indexable' => ['boolean'],
            'is_searchable' => ['boolean'],
            'show_in_menu' => ['boolean'],
            'show_in_footer' => ['boolean'],

            // Доступ
            'access_level' => ['sometimes', 'string', Rule::in(['public', 'registered', 'private'])],
        ];
    }
}
