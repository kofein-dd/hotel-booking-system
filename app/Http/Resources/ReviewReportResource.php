<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_id' => $this->review_id,
            'user_id' => $this->user_id,

            // Причина
            'reason' => $this->reason,
            'reason_display' => $this->getReasonText(),
            'description' => $this->description,
            'evidence' => $this->evidence,

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'status_color' => $this->getStatusColor(),
            'is_pending' => $this->isPending(),
            'is_resolved' => $this->isResolved(),

            // Решение
            'resolution' => $this->resolution,
            'resolution_display' => $this->getResolutionText(),
            'resolution_notes' => $this->resolution_notes,

            // Разрешение
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->format('Y-m-d H:i:s'),

            // Приоритет
            'priority' => $this->priority,
            'priority_stars' => str_repeat('★', $this->priority),

            // Теги
            'tags' => $this->tags,

            // Временные метки
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at,

            // Отношения
            'review' => new ReviewResource($this->whenLoaded('review')),
            'user' => new UserResource($this->whenLoaded('user')),
            'resolver' => new UserResource($this->whenLoaded('resolver')),

            // Дополнительные данные
            'review_content_preview' => $this->whenLoaded('review', function() {
                return substr($this->review->comment, 0, 100) .
                    (strlen($this->review->comment) > 100 ? '...' : '');
            }),

            // Для текущего пользователя
            'can_take_action' => $this->when($request->user(), function() use ($request) {
                return $request->user()->isAdmin() || $request->user()->isModerator();
            }),

            // История действий
            'action_history' => $this->when($request->user() && $request->user()->isAdmin(),
                function() {
                    return [
                        'created' => $this->created_at->format('Y-m-d H:i:s'),
                        'resolved' => $this->resolved_at?->format('Y-m-d H:i:s'),
                    ];
                }
            ),
        ];
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает рассмотрения',
            'investigating' => 'В процессе расследования',
            'resolved' => 'Решено',
            'dismissed' => 'Отклонено',
            'duplicate' => 'Дубликат',
            default => $this->status,
        };
    }
}
