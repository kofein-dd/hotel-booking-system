<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:reports', 'regex:/^[a-z0-9-]+$/'],

            // Тип и категория
            'type' => ['required', 'string', Rule::in([
                'financial', 'booking', 'user', 'room', 'revenue', 'occupancy', 'custom'
            ])],
            'category' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'adhoc'])],

            // Параметры
            'parameters' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],

            // Период
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            // Статус
            'status' => ['sometimes', 'string', Rule::in(['pending', 'processing', 'completed', 'failed', 'archived'])],

            // Расписание
            'is_scheduled' => ['boolean'],
            'schedule_cron' => ['nullable', 'string', 'max:50'],

            // Настройки
            'is_auto_generate' => ['boolean'],
            'send_email' => ['boolean'],
            'email_recipients' => ['nullable', 'array'],
            'email_recipients.*' => ['email'],

            // Создатель
            'created_by' => ['required', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефисы',
            'slug.unique' => 'Такой идентификатор уже используется',
            'date_from.before_or_equal' => 'Дата начала должна быть раньше или равна дате окончания',
            'date_to.after_or_equal' => 'Дата окончания должна быть позже или равна дате начала',
            'email_recipients.*.email' => 'Некорректный email адрес',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Название отчета',
            'type' => 'Тип отчета',
            'category' => 'Категория',
            'date_from' => 'Дата начала',
            'date_to' => 'Дата окончания',
            'schedule_cron' => 'Cron расписание',
            'email_recipients' => 'Получатели email',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'is_scheduled' => $this->boolean('is_scheduled', false),
            'is_auto_generate' => $this->boolean('is_auto_generate', false),
            'send_email' => $this->boolean('send_email', false),
            'created_by' => $this->input('created_by', auth()->id()),
            'status' => $this->input('status', 'pending'),
        ]);
    }
}
