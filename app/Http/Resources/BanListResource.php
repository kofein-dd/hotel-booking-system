<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BanListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'banned_by' => $this->banned_by,

            // Тип и причина
            'type' => $this->type,
            'type_display' => $this->getTypeDisplay(),
            'reason' => $this->reason,
            'reason_display' => $this->getReasonText(),
            'reason_description' => $this->reason_description,

            // Доказательства
            'evidence' => $this->evidence,

            // Срок бана
            'banned_at' => $this->banned_at->format('Y-m-d H:i:s'),
            'banned_until' => $this->banned_until?->format('Y-m-d H:i:s'),
            'remaining_time' => $this->getRemainingTime(),

            // Статус
            'is_active' => $this->isActive(),
            'is_permanent' => $this->isPermanent(),
            'is_temporary' => $this->isTemporary(),
            'is_expired' => $this->isExpired(),

            // Разбан
            'auto_unban' => $this->auto_unban,
            'unbanned_at' => $this->unbanned_at?->format('Y-m-d H:i:s'),
            'unbanned_by' => $this->unbanned_by,

            // Ограничения
            'restrict_booking' => $this->restrict_booking,
            'restrict_messaging' => $this->restrict_messaging,
            'restrict_reviews' => $this->restrict_reviews,
            'restrictions' => $this->restrictions,

            // История
            'warning_count' => $this->warning_count,
            'ban_history' => $this->ban_history,

            // Техническая информация
            'ip_address' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->ip_address
            ),
            'user_agent' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->user_agent
            ),

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'user' => new UserResource($this->whenLoaded('user')),
            'banned_by_user' => new UserResource($this->whenLoaded('bannedBy')),
            'unbanned_by_user' => new UserResource($this->whenLoaded('unbannedBy')),

            // Описание для отображения
            'display_description' => $this->getDisplayDescription(),
        ];
    }

    private function getTypeDisplay(): string
    {
        return match($this->type) {
            'temporary' => 'Временный бан',
            'permanent' => 'Постоянный бан',
            'warning' => 'Предупреждение',
            'shadow' => 'Скрытый бан',
            default => $this->type,
        };
    }
}
