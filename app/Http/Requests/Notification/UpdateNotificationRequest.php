<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Статус
            'status' => ['sometimes', 'string', Rule::in([
                'pending', 'sent', 'delivered', 'read', 'failed', 'cancelled'
            ])],

            // Каналы отправки
            'via_site' => ['boolean'],
            'via_email' => ['boolean'],
            'via_sms' => ['boolean'],
            'via_telegram' => ['boolean'],
            'via_push' => ['boolean'],

            // Даты
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],

            // Действие
            'action_url' => ['nullable', 'url', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:50'],

            // Флаги
            'is_important' => ['boolean'],
            'requires_action' => ['boolean'],

            // Отчет
            'delivery_report' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
