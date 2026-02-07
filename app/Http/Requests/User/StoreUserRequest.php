<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // В контроллере проверим авторизацию
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'role' => ['required', 'string', Rule::in(['admin', 'moderator', 'user'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'banned'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'preferences' => ['nullable', 'array'],
            'preferences.notifications' => ['nullable', 'boolean'],
            'preferences.email_notifications' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Поле "Имя" обязательно для заполнения',
            'email.required' => 'Поле "Email" обязательно для заполнения',
            'email.unique' => 'Пользователь с таким email уже существует',
            'password.required' => 'Поле "Пароль" обязательно для заполнения',
            'password.confirmed' => 'Пароли не совпадают',
            'phone.regex' => 'Неверный формат телефона',
            'role.in' => 'Неверная роль пользователя',
            'status.in' => 'Неверный статус пользователя',
            'avatar.image' => 'Файл должен быть изображением',
            'avatar.max' => 'Размер изображения не должен превышать 2MB',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Имя',
            'email' => 'Email',
            'password' => 'Пароль',
            'phone' => 'Телефон',
            'role' => 'Роль',
            'status' => 'Статус',
            'avatar' => 'Аватар',
        ];
    }
}
