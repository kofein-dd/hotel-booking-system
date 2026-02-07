<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reportId = $this->route('report') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('reports')->ignore($reportId)
            ],

            // Статус
            'status' => ['sometimes', 'string', Rule::in(['pending', 'processing', 'completed', 'failed', 'archived'])],

            // Результаты
            'data' => ['nullable', 'array'],
            'summary' => ['nullable', 'array'],
            'charts' => ['nullable', 'array'],

            // Файлы
            'file_path' => ['nullable', 'string', 'max:500'],
            'file_format' => ['nullable', 'string', 'max:10'],
            'file_size' => ['nullable', 'integer', 'min:0'],

            // Время выполнения
            'generated_at' => ['nullable', 'date'],
            'generation_time' => ['nullable', 'integer', 'min:0'],

            // Генератор
            'generated_by' => ['nullable', 'exists:users,id'],

            // Расписание
            'last_scheduled_run' => ['nullable', 'date'],
            'next_scheduled_run' => ['nullable', 'date'],

            // Настройки
            'is_auto_generate' => ['boolean'],
            'send_email' => ['boolean'],
        ];
    }
}
