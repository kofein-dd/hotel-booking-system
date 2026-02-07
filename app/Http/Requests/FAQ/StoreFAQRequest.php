<?php

namespace App\Http\Requests\FAQ;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFAQRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:5', 'max:500'],
            'answer' => ['required', 'string', 'min:10', 'max:5000'],
            'short_answer' => ['nullable', 'string', 'max:500'],

            // Категория
            'category' => ['required', 'string', 'max:100'],
            'category_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            // Приоритет и видимость
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'show_on_homepage' => ['boolean'],

            // Теги
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],

            // Автор
            'author_id' => ['required', 'exists:users,id'],

            // Связи
            'related_faq_id' => ['nullable', 'exists:faqs,id'],

            // Даты
            'published_at' => ['nullable', 'date', 'after_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.min' => 'Вопрос должен содержать не менее 5 символов',
            'answer.min' => 'Ответ должен содержать не менее 10 символов',
            'published_at.after_or_equal' => 'Дата публикации не может быть в прошлом',
        ];
    }

    public function attributes(): array
    {
        return [
            'question' => 'Вопрос',
            'answer' => 'Ответ',
            'category' => 'Категория',
            'priority' => 'Приоритет',
            'tags' => 'Теги',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_featured' => $this->boolean('is_featured', false),
            'show_on_homepage' => $this->boolean('show_on_homepage', false),
            'author_id' => $this->input('author_id', auth()->id()),
            'priority' => $this->input('priority', 0),
            'category_order' => $this->input('category_order', 0),
        ]);
    }
}
