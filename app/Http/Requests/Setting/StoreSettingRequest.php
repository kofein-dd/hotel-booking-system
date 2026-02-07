<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:50'],
            'key' => ['required', 'string', 'max:100', 'unique:settings', 'regex:/^[a-z0-9_.]+$/'],
            'value' => ['nullable', 'string', 'max:10000'],

            // Тип
            'type' => ['required', 'string', Rule::in(['string', 'integer', 'float', 'boolean', 'json', 'array', 'text'])],

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

    public function messages(): array
    {
        return [
            'key.regex' => 'Ключ может содержать только строчные латинские буквы, цифры, точки и подчеркивания',
            'key.unique' => 'Такой ключ уже существует',
            'value.max' => 'Значение не должно превышать 10000 символов',
        ];
    }

    public function attributes(): array
    {
        return [
            'group' => 'Группа',
            'key' => 'Ключ',
            'value' => 'Значение',
            'type' => 'Тип',
            'description' => 'Описание',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'is_public' => $this->boolean('is_public', false),
            'is_required' => $this->boolean('is_required', false),
            'order' => $this->input('order', 0),
            'type' => $this->input('type', 'string'),
        ]);
    }
}
