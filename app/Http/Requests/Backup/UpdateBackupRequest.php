<?php

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Статус
            'status' => ['sometimes', 'string', Rule::in(['pending', 'processing', 'completed', 'failed', 'verified', 'corrupted'])],

            // Файлы
            'filename' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:500'],
            'size' => ['nullable', 'integer', 'min:0'],

            // Даты
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'duration' => ['nullable', 'integer', 'min:0'],

            // Проверка
            'checksum' => ['nullable', 'string', 'max:100'],
            'is_verified' => ['boolean'],
            'verified_at' => ['nullable', 'date'],
            'verified_by' => ['nullable', 'exists:users,id'],

            // Результаты
            'logs' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string', 'max:1000'],
            'statistics' => ['nullable', 'array'],
        ];
    }
}
