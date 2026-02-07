<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'name' => $this->name,
            'type' => $this->type,
            'slug' => $this->slug,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'price_per_night' => (float) $this->price_per_night,
            'total_rooms' => $this->total_rooms,
            'available_rooms' => $this->available_rooms,
            'amenities' => $this->amenities,
            'photos' => $this->photos ? array_map(fn($photo) => asset('storage/' . $photo), $this->photos) : [],
            'size' => $this->size,
            'bed_types' => $this->bed_types,
            'view' => $this->view,
            'extra_services' => $this->extra_services,
            'status' => $this->status,
            'is_available' => $this->isAvailable(),
            'order' => $this->order,
            'main_photo' => $this->main_photo ? asset('storage/' . $this->main_photo) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Статистика
            'bookings_count' => $this->whenLoaded('bookings', fn() => $this->bookings->count()),
            'reviews_count' => $this->whenLoaded('reviews', fn() => $this->reviews->count()),
            'average_rating' => $this->whenLoaded('reviews', function() {
                if ($this->reviews->count() > 0) {
                    return round($this->reviews->avg('rating_overall'), 1);
                }
                return null;
            }),

            // Отношения
            'hotel' => new HotelResource($this->whenLoaded('hotel')),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),

            // Расчет цены для периода
            'price_for_period' => $this->when($request->has(['check_in', 'check_out']), function() use ($request) {
                $checkIn = \Carbon\Carbon::parse($request->check_in);
                $checkOut = \Carbon\Carbon::parse($request->check_out);
                $nights = $checkIn->diffInDays($checkOut);
                return $this->getPriceForDates($nights);
            }),
        ];
    }
}
