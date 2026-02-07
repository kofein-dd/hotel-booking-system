<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settingId = $this->route('setting') ?? $this->route('id');

        return [
            'group' => ['sometimes', 'string', 'max:50'],
            'key' => [
                'sometimes',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.]+$/',
                Rule::unique('settings')->ignore($settingId)
            ],
            'value' => ['nullable', 'string', 'max:10000'],

            // Тип
            'type' => ['sometimes', 'string', Rule::in(['string', 'integer', 'float', 'boolean', 'json', 'array', 'text'])],

            // Метаданные
            'description' => ['nullable', 'string', 'max:500'],
            'options' => ['nullable', 'array'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],

            // Видимость
            'is_public' => ['boolean'],
            'is_required' => ['boolean'],

            // Валидация
            'validation_rules' => ['nullable', 'array'],
        ];
    }
}
