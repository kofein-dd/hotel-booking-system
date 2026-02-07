<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'name' => $this->name,
            'slug' => $this->slug,

            // Тип и категория
            'type' => $this->type,
            'type_display' => $this->getTypeDisplay(),
            'category' => $this->category,
            'category_display' => $this->getCategoryDisplay(),

            // Параметры
            'parameters' => $this->parameters,
            'filters' => $this->filters,

            // Период
            'date_from' => $this->date_from?->format('Y-m-d'),
            'date_to' => $this->date_to?->format('Y-m-d'),
            'period_text' => $this->getPeriodText(),

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'is_completed' => $this->isCompleted(),
            'is_processing' => $this->isProcessing(),
            'is_failed' => $this->isFailed(),

            // Результаты
            'data' => $this->when(
                $this->is_completed && ($request->user() && $request->user()->isAdmin()),
                fn() => $this->data
            ),
            'summary' => $this->when(
                $this->is_completed,
                fn() => $this->summary
            ),
            'charts' => $this->when(
                $this->is_completed,
                fn() => $this->charts
            ),

            // Файлы
            'file_path' => $this->file_path,
            'file_format' => $this->file_format,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size ? $this->formatFileSize($this->file_size) : null,
            'has_file' => $this->hasFile(),
            'download_url' => $this->getDownloadUrl(),

            // Время выполнения
            'generated_at' => $this->generated_at?->format('Y-m-d H:i:s'),
            'generation_time' => $this->generation_time,
            'generation_time_formatted' => $this->generation_time ? $this->formatGenerationTime($this->generation_time) : null,

            // Создатели
            'created_by' => $this->created_by,
            'generated_by' => $this->generated_by,

            // Расписание
            'is_scheduled' => $this->is_scheduled,
            'schedule_cron' => $this->schedule_cron,
            'last_scheduled_run' => $this->last_scheduled_run?->format('Y-m-d H:i:s'),
            'next_scheduled_run' => $this->next_scheduled_run?->format('Y-m-d H:i:s'),

            // Настройки
            'is_auto_generate' => $this->is_auto_generate,
            'send_email' => $this->send_email,
            'email_recipients' => $this->email_recipients,

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'creator' => new UserResource($this->whenLoaded('creator')),
            'generator' => new UserResource($this->whenLoaded('generator')),

            // Ограниченный доступ к данным
            'limited_summary' => $this->when(
                $this->is_completed && !($request->user() && $request->user()->isAdmin()),
                function() {
                    // Возвращаем только основные метрики для не-админов
                    return [
                        'generated_at' => $this->generated_at?->format('Y-m-d H:i:s'),
                        'record_count' => count($this->data ?? []),
                        'has_charts' => !empty($this->charts),
                    ];
                }
            ),
        ];
    }

    private function getTypeDisplay(): string
    {
        return match($this->type) {
            'financial' => 'Финансовый',
            'booking' => 'Бронирования',
            'user' => 'Пользователи',
            'room' => 'Номера',
            'revenue' => 'Выручка',
            'occupancy' => 'Заполняемость',
            'custom' => 'Пользовательский',
            default => $this->type,
        };
    }

    private function getCategoryDisplay(): string
    {
        return match($this->category) {
            'daily' => 'Ежедневный',
            'weekly' => 'Еженедельный',
            'monthly' => 'Ежемесячный',
            'quarterly' => 'Квартальный',
            'yearly' => 'Годовой',
            'adhoc' => 'По требованию',
            default => $this->category,
        };
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает генерации',
            'processing' => 'В обработке',
            'completed' => 'Завершен',
            'failed' => 'Ошибка',
            'archived' => 'В архиве',
            default => $this->status,
        };
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    private function formatGenerationTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' сек';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;
            return $minutes . ' мин ' . $seconds . ' сек';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . ' ч ' . $minutes . ' мин';
        }
    }
}
