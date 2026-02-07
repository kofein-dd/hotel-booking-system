<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'backup_number' => $this->backup_number,

            // Тип и цель
            'type' => $this->type,
            'type_display' => $this->getTypeDisplay(),
            'purpose' => $this->purpose,
            'purpose_display' => $this->getPurposeDisplay(),

            // Файлы
            'filename' => $this->filename,
            'path' => $this->path,
            'storage_disk' => $this->storage_disk,
            'size' => $this->size,
            'size_formatted' => $this->getSizeFormatted(),

            // База данных
            'database_name' => $this->database_name,
            'tables_count' => $this->tables_count,

            // Файлы системы
            'files_count' => $this->files_count,
            'included_directories' => $this->included_directories,
            'excluded_directories' => $this->excluded_directories,

            // Статус
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'is_verified' => $this->is_verified,
            'is_expired' => $this->isExpired(),

            // Даты
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'duration' => $this->duration,
            'duration_formatted' => $this->getDurationFormatted(),

            // Создатель
            'created_by' => $this->created_by,

            // Проверка
            'checksum' => $this->checksum,
            'verified_at' => $this->verified_at?->format('Y-m-d H:i:s'),
            'verified_by' => $this->verified_by,

            // Расписание
            'is_scheduled' => $this->is_scheduled,
            'schedule_cron' => $this->schedule_cron,

            // Хранилище
            'retention_days' => $this->retention_days,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),

            // Результаты
            'logs' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->logs
            ),
            'error_message' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->error_message
            ),
            'statistics' => $this->statistics,

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Отношения
            'creator' => new UserResource($this->whenLoaded('creator')),
            'verifier' => new UserResource($this->whenLoaded('verifier')),

            // Действия
            'download_url' => $this->getDownloadUrl(),
            'has_file' => $this->hasFile(),
            'can_download' => $this->hasFile() && $this->is_completed,

            // Форматированная информация
            'backup_info' => $this->getBackupInfo(),
        ];
    }

    private function getTypeDisplay(): string
    {
        return match($this->type) {
            'full' => 'Полный бекап',
            'database' => 'Только база данных',
            'files' => 'Только файлы',
            'incremental' => 'Инкрементальный',
            default => $this->type,
        };
    }

    private function getPurposeDisplay(): string
    {
        return match($this->purpose) {
            'scheduled' => 'По расписанию',
            'manual' => 'Ручной',
            'before_update' => 'Перед обновлением',
            'emergency' => 'Аварийный',
            default => $this->purpose,
        };
    }

    private function getStatusDisplay(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает',
            'processing' => 'В процессе',
            'completed' => 'Завершен',
            'failed' => 'Ошибка',
            'verified' => 'Проверен',
            'corrupted' => 'Поврежден',
            default => $this->status,
        };
    }

    private function getBackupInfo(): array
    {
        $info = [
            'type' => $this->getTypeDisplay(),
            'purpose' => $this->getPurposeDisplay(),
            'size' => $this->getSizeFormatted(),
        ];

        if ($this->database_name) {
            $info['database'] = $this->database_name;
            if ($this->tables_count) {
                $info['tables'] = $this->tables_count;
            }
        }

        if ($this->files_count) {
            $info['files'] = $this->files_count;
        }

        if ($this->duration) {
            $info['duration'] = $this->getDurationFormatted();
        }

        if ($this->is_verified) {
            $info['verified'] = 'Да';
        }

        return $info;
    }
}
