<?php

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Тип и цель
            'type' => ['required', 'string', Rule::in(['full', 'database', 'files', 'incremental'])],
            'purpose' => ['required', 'string', Rule::in(['scheduled', 'manual', 'before_update', 'emergency'])],

            // Файлы
            'filename' => ['nullable', 'string', 'max:255'],
            'storage_disk' => ['nullable', 'string', 'max:50'],

            // База данных
            'database_name' => ['nullable', 'string', 'max:100'],

            // Файлы системы
            'included_directories' => ['nullable', 'array'],
            'included_directories.*' => ['string', 'max:255'],
            'excluded_directories' => ['nullable', 'array'],
            'excluded_directories.*' => ['string', 'max:255'],

            // Расписание
            'is_scheduled' => ['boolean'],
            'schedule_cron' => ['nullable', 'string', 'max:50'],

            // Хранилище
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'], // до 10 лет

            // Создатель
            'created_by' => ['nullable', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'retention_days.max' => 'Максимальный срок хранения: 10 лет',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Тип бекапа',
            'purpose' => 'Цель',
            'storage_disk' => 'Диск хранилища',
            'retention_days' => 'Срок хранения (дни)',
            'schedule_cron' => 'Cron расписание',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'is_scheduled' => $this->boolean('is_scheduled', false),
            'created_by' => $this->input('created_by', auth()->id()),
            'retention_days' => $this->input('retention_days', 30),
            'storage_disk' => $this->input('storage_disk', 'local'),
        ]);
    }
}
