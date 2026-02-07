<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'booking_id' => ['required', 'exists:bookings,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'hotel_id' => ['required', 'exists:hotels,id'],

            // Рейтинги
            'rating_overall' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_cleanliness' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_comfort' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_location' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_service' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_value' => ['nullable', 'integer', 'min:1', 'max:5'],

            // Контент
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'comment' => ['required', 'string', 'min:20', 'max:2000'],
            'pros' => ['nullable', 'string', 'max:1000'],
            'cons' => ['nullable', 'string', 'max:1000'],

            // Медиа
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'videos' => ['nullable', 'array', 'max:2'],
            'videos.*' => ['url', 'starts_with:https://www.youtube.com/,https://vimeo.com/'],

            // Статус
            'status' => ['sometimes', 'string', Rule::in(['pending', 'approved', 'rejected', 'hidden'])],
        ];
    }

    public function messages(): array
    {
        return [
            'rating_overall.required' => 'Пожалуйста, поставьте общую оценку',
            'rating_overall.min' => 'Минимальная оценка: 1 звезда',
            'rating_overall.max' => 'Максимальная оценка: 5 звезд',
            'title.min' => 'Заголовок должен содержать не менее 5 символов',
            'comment.min' => 'Отзыв должен содержать не менее 20 символов',
            'photos.max' => 'Можно загрузить не более 5 фотографий',
            'videos.max' => 'Можно добавить не более 2 видео',
            'videos.*.starts_with' => 'Поддерживаются только YouTube и Vimeo ссылки',
        ];
    }

    public function attributes(): array
    {
        return [
            'rating_overall' => 'Общая оценка',
            'rating_cleanliness' => 'Чистота',
            'rating_comfort' => 'Комфорт',
            'rating_location' => 'Расположение',
            'rating_service' => 'Обслуживание',
            'rating_value' => 'Соотношение цены и качества',
            'title' => 'Заголовок',
            'comment' => 'Отзыв',
            'pros' => 'Плюсы',
            'cons' => 'Минусы',
            'photos' => 'Фотографии',
        ];
    }
}
