<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],

            // Тип и категория
            'type' => ['required', 'string', Rule::in([
                'system', 'booking', 'payment', 'reminder', 'promotion', 'support', 'review', 'admin', 'other'
            ])],
            'category' => ['required', 'string', Rule::in(['info', 'success', 'warning', 'error', 'important'])],

            // Содержание
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'data' => ['nullable', 'array'],

            // Каналы отправки
            'via_site' => ['boolean'],
            'via_email' => ['boolean'],
            'via_sms' => ['boolean'],
            'via_telegram' => ['boolean'],
            'via_push' => ['boolean'],

            // Расписание
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],

            // Действие
            'action_url' => ['nullable', 'url', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:50'],

            // Флаги
            'is_important' => ['boolean'],
            'requires_action' => ['boolean'],
            'is_broadcast' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after_or_equal' => 'Дата отправки не может быть в прошлом',
            'subject.min' => 'Тема должна содержать не менее 3 символов',
            'message.min' => 'Сообщение должно содержать не менее 10 символов',
            'action_text.max' => 'Текст кнопки не должен превышать 50 символов',
        ];
    }

    public function attributes(): array
    {
        return [
            'subject' => 'Тема',
            'message' => 'Сообщение',
            'scheduled_at' => 'Дата отправки',
            'action_url' => 'Ссылка для действия',
            'action_text' => 'Текст кнопки',
        ];
    }

    public function prepareForValidation()
    {
        // Устанавливаем значения по умолчанию для булевых полей
        $this->merge([
            'via_site' => $this->boolean('via_site', true),
            'via_email' => $this->boolean('via_email', false),
            'via_sms' => $this->boolean('via_sms', false),
            'via_telegram' => $this->boolean('via_telegram', false),
            'via_push' => $this->boolean('via_push', false),
            'is_important' => $this->boolean('is_important', false),
            'requires_action' => $this->boolean('requires_action', false),
            'is_broadcast' => $this->boolean('is_broadcast', false),
        ]);
    }
}
