<?php

namespace App\Http\Requests\ReviewReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_id' => ['required', 'exists:reviews,id'],
            'user_id' => ['required', 'exists:users,id'],

            // Причина
            'reason' => ['required', 'string', Rule::in([
                'spam', 'inappropriate', 'false_information', 'harassment',
                'conflict_of_interest', 'fake_review', 'other'
            ])],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string', 'max:500'], // URLs или описания

            // Приоритет
            'priority' => ['nullable', 'integer', 'min:1', 'max:5'],

            // Теги
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_id.exists' => 'Отзыв не найден',
            'user_id.exists' => 'Пользователь не найден',
            'priority.min' => 'Минимальный приоритет: 1',
            'priority.max' => 'Максимальный приоритет: 5',
        ];
    }

    public function attributes(): array
    {
        return [
            'review_id' => 'Отзыв',
            'reason' => 'Причина жалобы',
            'description' => 'Описание',
            'evidence' => 'Доказательства',
            'priority' => 'Приоритет',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'user_id' => $this->input('user_id', auth()->id()),
            'priority' => $this->input('priority', 1),
        ]);
    }
}
