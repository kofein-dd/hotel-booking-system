<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'photos' => $this->photos ? array_map(fn($photo) => asset('storage/' . $photo), $this->photos) : [],
            'videos' => $this->videos,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'coordinates' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'contact_info' => [
                'phone' => $this->phone,
                'email' => $this->email,
                'website' => $this->website,
                'additional' => $this->contact_info,
            ],
            'amenities' => $this->amenities,
            'social_links' => $this->social_links,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'non_working_days' => $this->non_working_days,
            'main_photo' => $this->main_photo ? asset('storage/' . $this->main_photo) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Статистика
            'rooms_count' => $this->whenLoaded('rooms', fn() => $this->rooms->count()),
            'active_rooms_count' => $this->whenLoaded('activeRooms', fn() => $this->activeRooms->count()),
            'average_rating' => $this->whenLoaded('reviews', function() {
                if ($this->reviews->count() > 0) {
                    return round($this->reviews->avg('rating_overall'), 1);
                }
                return null;
            }),

            // Отношения
            'rooms' => RoomResource::collection($this->whenLoaded('rooms')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
