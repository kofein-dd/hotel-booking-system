<?php

namespace App\Http\Requests\FAQ;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFAQRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['sometimes', 'string', 'min:5', 'max:500'],
            'answer' => ['sometimes', 'string', 'min:10', 'max:5000'],
            'short_answer' => ['nullable', 'string', 'max:500'],

            // Категория
            'category' => ['sometimes', 'string', 'max:100'],
            'category_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            // Приоритет и видимость
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'show_on_homepage' => ['boolean'],

            // Теги
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],

            // Редактор
            'last_edited_by' => ['nullable', 'exists:users,id'],

            // Связи
            'related_faq_id' => ['nullable', 'exists:faqs,id'],

            // Даты
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'last_edited_by' => $this->input('last_edited_by', auth()->id()),
        ]);
    }
}
