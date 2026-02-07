<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roomId = $this->route('room') ?? $this->route('id');

        return [
            'hotel_id' => ['sometimes', 'exists:hotels,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('rooms')->ignore($roomId)
            ],
            'description' => ['sometimes', 'string', 'min:30', 'max:2000'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'price_per_night' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'total_rooms' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'available_rooms' => ['sometimes', 'integer', 'min:0', 'max:100'],
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
            'status' => ['sometimes', 'string', Rule::in(['available', 'unavailable', 'maintenance'])],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'available_rooms.max' => 'Доступных номеров не может быть больше общего количества',
        ];
    }
}
