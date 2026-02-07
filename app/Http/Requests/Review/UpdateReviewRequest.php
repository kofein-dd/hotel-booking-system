<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Рейтинги
            'rating_overall' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'rating_cleanliness' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_comfort' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_location' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_service' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_value' => ['nullable', 'integer', 'min:1', 'max:5'],

            // Контент
            'title' => ['sometimes', 'string', 'min:5', 'max:200'],
            'comment' => ['sometimes', 'string', 'min:20', 'max:2000'],
            'pros' => ['nullable', 'string', 'max:1000'],
            'cons' => ['nullable', 'string', 'max:1000'],

            // Медиа
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],

            // Статус (для админов/модераторов)
            'status' => ['sometimes', 'string', Rule::in(['pending', 'approved', 'rejected', 'hidden'])],

            // Ответ отеля
            'hotel_reply' => ['nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hotel_reply.min' => 'Ответ должен содержать не менее 5 символов',
        ];
    }
}
