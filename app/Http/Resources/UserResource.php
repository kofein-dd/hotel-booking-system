<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'role' => $this->role,
            'status' => $this->status,
            'is_banned' => $this->isBanned(),
            'banned_until' => $this->banned_until,
            'preferences' => $this->preferences,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения (только при загрузке)
            'bookings_count' => $this->whenLoaded('bookings', fn() => $this->bookings->count()),
            'reviews_count' => $this->whenLoaded('reviews', fn() => $this->reviews->count()),

            // Дополнительные данные (по запросу)
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'active_ban' => $this->when($request->has('include_ban'), fn() =>
            $this->bans()->where('is_active', true)->first()
            ),
        ];
    }
}
