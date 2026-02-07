<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId)
            ],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'moderator', 'user'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'banned'])],
            'banned_until' => ['nullable', 'date', 'after:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'preferences' => ['nullable', 'array'],
            'preferences.notifications' => ['nullable', 'boolean'],
            'preferences.email_notifications' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Пользователь с таким email уже существует',
            'banned_until.after' => 'Дата бана должна быть в будущем',
            'avatar.max' => 'Размер изображения не должен превышать 2MB',
        ];
    }
}
