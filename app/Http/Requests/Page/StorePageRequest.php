<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pages', 'regex:/^[a-z0-9-]+$/'],
            'content' => ['nullable', 'string', 'max:50000'],
            'excerpt' => ['nullable', 'string', 'max:500'],

            // SEO
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'array'],
            'meta_keywords.*' => ['string', 'max:50'],

            // Тип и статус
            'type' => ['required', 'string', Rule::in(['page', 'home', 'contact', 'about', 'terms', 'privacy', 'custom'])],
            'status' => ['required', 'string', Rule::in(['published', 'draft', 'scheduled', 'private', 'archived'])],

            // Даты
            'published_at' => ['nullable', 'date', 'after_or_equal:now'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],

            // Медиа
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],

            // Структура
            'parent_id' => ['nullable', 'exists:pages,id'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'template' => ['nullable', 'string', 'max:100'],

            // Настройки
            'is_indexable' => ['boolean'],
            'is_searchable' => ['boolean'],
            'show_in_menu' => ['boolean'],
            'show_in_footer' => ['boolean'],

            // Доступ
            'access_level' => ['required', 'string', Rule::in(['public', 'registered', 'private'])],

            // Автор
            'author_id' => ['required', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефисы',
            'slug.unique' => 'Такой URL уже используется',
            'published_at.after_or_equal' => 'Дата публикации не может быть в прошлом',
            'scheduled_at.after' => 'Дата планирования должна быть в будущем',
            'gallery.max' => 'Можно загрузить не более 10 изображений в галерею',
            'parent_id.exists' => 'Родительская страница не найдена',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Заголовок',
            'slug' => 'URL',
            'content' => 'Содержимое',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
            'featured_image' => 'Обложка',
            'parent_id' => 'Родительская страница',
            'access_level' => 'Уровень доступа',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'is_indexable' => $this->boolean('is_indexable', true),
            'is_searchable' => $this->boolean('is_searchable', true),
            'show_in_menu' => $this->boolean('show_in_menu', true),
            'show_in_footer' => $this->boolean('show_in_footer', false),
            'author_id' => $this->input('author_id', auth()->id()),
            'order' => $this->input('order', 0),
        ]);
    }
}
