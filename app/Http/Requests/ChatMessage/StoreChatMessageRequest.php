<?php

namespace App\Http\Requests\ChatMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'uuid'],
            'user_id' => ['required', 'exists:users,id'],
            'admin_id' => ['nullable', 'exists:users,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],

            // Сообщение
            'message' => ['required', 'string', 'min:1', 'max:5000'],

            // Вложения
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt', 'max:10240'], // 10MB

            // Тип сообщения
            'message_type' => ['required', 'string', Rule::in(['text', 'image', 'document', 'system'])],

            // Флаг админского сообщения
            'is_admin_message' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Сообщение не может быть пустым',
            'message.max' => 'Сообщение не должно превышать 5000 символов',
            'attachments.max' => 'Можно прикрепить не более 5 файлов',
            'attachments.*.max' => 'Размер каждого файла не должен превышать 10MB',
            'attachments.*.mimes' => 'Поддерживаются файлы: jpg, png, gif, pdf, doc, docx, txt',
        ];
    }

    public function attributes(): array
    {
        return [
            'message' => 'Сообщение',
            'attachments' => 'Вложения',
            'conversation_id' => 'ID диалога',
        ];
    }

    public function prepareForValidation()
    {
        // Устанавливаем значения по умолчанию
        $this->merge([
            'is_admin_message' => $this->boolean('is_admin_message', false),
            'message_type' => $this->input('message_type', 'text'),
        ]);
    }
}
