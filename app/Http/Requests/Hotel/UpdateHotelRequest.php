<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hotelId = $this->route('hotel') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'min:50', 'max:5000'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('hotels')->ignore($hotelId)
            ],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'videos' => ['nullable', 'array', 'max:5'],
            'videos.*' => ['url', 'starts_with:https://www.youtube.com/,https://vimeo.com/'],
            'address' => ['sometimes', 'string', 'max:500'],
            'city' => ['sometimes', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'email' => ['sometimes', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_info' => ['nullable', 'array'],
            'amenities' => ['nullable', 'array'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url'],
            'social_links.instagram' => ['nullable', 'url'],
            'social_links.twitter' => ['nullable', 'url'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
            'non_working_days' => ['nullable', 'array'],
            'non_working_days.*' => ['date', 'after:today'],
        ];
    }
}
