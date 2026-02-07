<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'slug' => ['required', 'string', 'max:255', 'unique:hotels', 'regex:/^[a-z0-9-]+$/'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'videos' => ['nullable', 'array', 'max:5'],
            'videos.*' => ['url', 'starts_with:https://www.youtube.com/,https://vimeo.com/'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_info' => ['nullable', 'array'],
            'amenities' => ['nullable', 'array'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url'],
            'social_links.instagram' => ['nullable', 'url'],
            'social_links.twitter' => ['nullable', 'url'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
            'non_working_days' => ['nullable', 'array'],
            'non_working_days.*' => ['date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Название отеля обязательно',
            'description.min' => 'Описание должно содержать не менее 50 символов',
            'slug.regex' => 'Slug может содержать только латинские буквы, цифры и дефисы',
            'slug.unique' => 'Такой URL уже используется',
            'photos.max' => 'Можно загрузить не более 10 фотографий',
            'photos.*.max' => 'Размер каждой фотографии не должен превышать 5MB',
            'videos.*.starts_with' => 'Поддерживаются только YouTube и Vimeo ссылки',
            'latitude.between' => 'Широта должна быть в диапазоне от -90 до 90',
            'longitude.between' => 'Долгота должна быть в диапазоне от -180 до 180',
            'phone.regex' => 'Неверный формат телефона',
            'non_working_days.*.after' => 'Даты нерабочих дней должны быть в будущем',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Название отеля',
            'description' => 'Описание',
            'slug' => 'URL',
            'photos' => 'Фотографии',
            'videos' => 'Видео',
            'address' => 'Адрес',
            'city' => 'Город',
            'country' => 'Страна',
            'latitude' => 'Широта',
            'longitude' => 'Долгота',
            'phone' => 'Телефон',
            'email' => 'Email',
            'website' => 'Веб-сайт',
        ];
    }
}
