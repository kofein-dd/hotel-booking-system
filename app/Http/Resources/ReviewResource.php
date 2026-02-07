<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'booking_id' => $this->booking_id,
            'room_id' => $this->room_id,
            'hotel_id' => $this->hotel_id,

            // Рейтинги
            'rating_overall' => $this->rating_overall,
            'rating_cleanliness' => $this->rating_cleanliness,
            'rating_comfort' => $this->rating_comfort,
            'rating_location' => $this->rating_location,
            'rating_service' => $this->rating_service,
            'rating_value' => $this->rating_value,
            'average_ratings' => $this->getAverageRatings(),
            'stars_html' => $this->getStarsHtml(),
            'calculated_overall' => $this->calculateOverallRating(),

            // Контент
            'title' => $this->title,
            'comment' => $this->comment,
            'pros' => $this->pros,
            'cons' => $this->cons,

            // Медиа
            'photos' => $this->photos ? array_map(fn($photo) => asset('storage/' . $photo), $this->photos) : [],
            'videos' => $this->videos,

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'is_approved' => $this->isApproved(),
            'is_pending' => $this->isPending(),
            'can_be_edited' => $this->canBeEdited(),

            // Ответ отеля
            'hotel_reply' => $this->hotel_reply,
            'hotel_reply_at' => $this->hotel_reply_at?->format('Y-m-d H:i:s'),

            // Полезность
            'helpful_count' => $this->helpful_count,
            'unhelpful_count' => $this->unhelpful_count,
            'helpfulness_rating' => $this->getHelpfulnessRating(),

            // Временные метки
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at,

            // Отношения
            'user' => new UserResource($this->whenLoaded('user')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'hotel' => new HotelResource($this->whenLoaded('hotel')),
            'reply_author' => new UserResource($this->whenLoaded('replyAuthor')),
            'reports' => ReviewReportResource::collection($this->whenLoaded('reports')),

            // Дополнительные данные
            'user_has_reported' => $this->when($request->user(), function() use ($request) {
                return $this->reports()->where('user_id', $request->user()->id)->exists();
            }),
        ];
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'На модерации',
            'approved' => 'Одобрено',
            'rejected' => 'Отклонено',
            'hidden' => 'Скрыто',
            default => $this->status,
        };
    }
}
