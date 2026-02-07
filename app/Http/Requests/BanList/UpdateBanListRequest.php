<?php

namespace App\Http\Requests\BanList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBanListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Статус
            'is_active' => ['boolean'],

            // Срок бана
            'banned_until' => ['nullable', 'date', 'after:now'],

            // Авторазбан
            'auto_unban' => ['boolean'],

            // Ограничения
            'restrict_booking' => ['boolean'],
            'restrict_messaging' => ['boolean'],
            'restrict_reviews' => ['boolean'],

            // История
            'warning_count' => ['nullable', 'integer', 'min:0'],
            'ban_history' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'banned_until.after' => 'Дата окончания бана должна быть в будущем',
        ];
    }
}
