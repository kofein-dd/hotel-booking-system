<?php

namespace App\Http\Requests\BanList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBanListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'banned_by' => ['required', 'exists:users,id'],

            // Тип и причина
            'type' => ['required', 'string', Rule::in(['temporary', 'permanent', 'warning', 'shadow'])],
            'reason' => ['required', 'string', Rule::in([
                'spam', 'abuse', 'fraud', 'multiple_accounts', 'chargeback',
                'policy_violation', 'inappropriate_content', 'other'
            ])],
            'reason_description' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'array'],

            // Срок бана
            'banned_until' => ['nullable', 'date', 'after:now', 'required_if:type,temporary'],

            // Авторазбан
            'auto_unban' => ['boolean'],

            // Ограничения
            'restrict_booking' => ['boolean'],
            'restrict_messaging' => ['boolean'],
            'restrict_reviews' => ['boolean'],
            'restrictions' => ['nullable', 'array'],

            // Техническая информация
            'ip_address' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Пользователь не найден',
            'banned_by.exists' => 'Администратор не найден',
            'banned_until.required_if' => 'Для временного бана необходимо указать дату окончания',
            'banned_until.after' => 'Дата окончания бана должна быть в будущем',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Пользователь',
            'banned_by' => 'Администратор',
            'type' => 'Тип бана',
            'reason' => 'Причина',
            'banned_until' => 'Дата окончания',
            'restrict_booking' => 'Запрет бронирования',
            'restrict_messaging' => 'Запрет сообщений',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'auto_unban' => $this->boolean('auto_unban', true),
            'restrict_booking' => $this->boolean('restrict_booking', true),
            'restrict_messaging' => $this->boolean('restrict_messaging', false),
            'restrict_reviews' => $this->boolean('restrict_reviews', false),
            'banned_by' => $this->input('banned_by', auth()->id()),
            'ip_address' => $this->input('ip_address', request()->ip()),
            'user_agent' => $this->input('user_agent', request()->userAgent()),
        ]);
    }
}
