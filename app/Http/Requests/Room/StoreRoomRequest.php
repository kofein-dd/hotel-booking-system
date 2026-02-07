<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'exists:hotels,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:255', 'unique:rooms', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10'],
            'price_per_night' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'total_rooms' => ['required', 'integer', 'min:1', 'max:100'],
            'available_rooms' => ['required', 'integer', 'min:0', 'max:100'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'size' => ['nullable', 'string', 'max:50'],
            'bed_types' => ['nullable', 'array'],
            'bed_types.*' => ['string', 'max:50'],
            'view' => ['nullable', 'array'],
            'view.*' => ['string', 'max:100'],
            'extra_services' => ['nullable', 'array'],
            'extra_services.*' => ['string', 'max:100'],
            'status' => ['required', 'string', Rule::in(['available', 'unavailable', 'maintenance'])],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'hotel_id.exists' => 'Указанный отель не существует',
            'capacity.min' => 'Вместимость не может быть менее 1 человека',
            'capacity.max' => 'Вместимость не может быть более 10 человек',
            'price_per_night.min' => 'Цена не может быть отрицательной',
            'total_rooms.min' => 'Количество номеров не может быть менее 1',
            'available_rooms.max' => 'Доступных номеров не может быть больше общего количества',
            'photos.max' => 'Можно загрузить не более 10 фотографий',
            'photos.*.max' => 'Размер каждой фотографии не должен превышать 5MB',
            'slug.regex' => 'Slug может содержать только латинские буквы, цифры и дефисы',
            'slug.unique' => 'Такой URL уже используется',
        ];
    }

    public function attributes(): array
    {
        return [
            'hotel_id' => 'Отель',
            'name' => 'Название номера',
            'type' => 'Тип номера',
            'slug' => 'URL',
            'description' => 'Описание',
            'capacity' => 'Вместимость',
            'price_per_night' => 'Цена за ночь',
            'total_rooms' => 'Общее количество номеров',
            'available_rooms' => 'Доступные номера',
            'photos' => 'Фотографии',
            'size' => 'Размер номера',
            'bed_types' => 'Типы кроватей',
            'view' => 'Вид из окна',
            'extra_services' => 'Дополнительные услуги',
        ];
    }
}
