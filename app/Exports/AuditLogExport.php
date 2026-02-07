<?php

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

class AuditLogExport extends GenericExport
{
    public function __construct(Collection $collection)
    {
        parent::__construct(
            $collection,
            'Аудит-логи',
            [
                'ID',
                'Пользователь',
                'Действие',
                'Модель',
                'ID модели',
                'Старые данные',
                'Новые данные',
                'IP адрес',
                'User Agent',
                'Дата создания',
            ]
        );
    }

    /**
     * Преобразование данных аудит-логов
     */
    public function map($log): array
    {
        return [
            $log->id,
            $log->user ? $log->user->email : 'Система',
            $log->action,
            $log->model_type,
            $log->model_id,
            $this->formatJsonData($log->old_data),
            $this->formatJsonData($log->new_data),
            $log->ip_address,
            $log->user_agent,
            $log->created_at->format('d.m.Y H:i:s'),
        ];
    }

    /**
     * Форматирование JSON данных
     */
    private function formatJsonData(?array $data): string
    {
        if (empty($data)) {
            return '-';
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
