<?php

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Поиск
            'search' => ['nullable', 'string', 'max:255'],

            // Фильтры
            'user_id' => ['nullable', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'model_type' => ['nullable', 'string', 'max:100'],
            'model_id' => ['nullable', 'integer'],
            'level' => ['nullable', 'string', Rule::in(['info', 'warning', 'error', 'critical'])],

            // Даты
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            // Пагинация
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],

            // Сортировка
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'action', 'level'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'Дата начала должна быть раньше или равна дате окончания',
            'date_to.after_or_equal' => 'Дата окончания должна быть позже или равна дате начала',
            'per_page.max' => 'Максимальное количество записей на странице: 100',
        ];
    }

    public function prepareForValidation()
    {
        // Устанавливаем значения по умолчанию
        $this->merge([
            'per_page' => $this->input('per_page', 20),
            'page' => $this->input('page', 1),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_order' => $this->input('sort_order', 'desc'),
        ]);
    }
}
