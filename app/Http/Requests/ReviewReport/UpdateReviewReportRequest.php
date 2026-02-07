<?php

namespace App\Http\Requests\ReviewReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Статус
            'status' => ['sometimes', 'string', Rule::in(['pending', 'investigating', 'resolved', 'dismissed', 'duplicate'])],

            // Решение
            'resolution' => ['nullable', 'string', Rule::in([
                'review_hidden', 'review_edited', 'user_warned', 'user_banned', 'no_action', 'pending'
            ])],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],

            // Разрешение
            'resolved_by' => ['nullable', 'exists:users,id'],

            // Приоритет
            'priority' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolved_by.exists' => 'Пользователь не найден',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'resolved_by' => $this->input('resolved_by', auth()->id()),
        ]);
    }
}
