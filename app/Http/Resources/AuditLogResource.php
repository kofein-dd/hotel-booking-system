<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_number' => $this->log_number,

            // Пользователь
            'user_id' => $this->user_id,
            'user_ip' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->user_ip
            ),
            'user_agent' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->user_agent
            ),

            // Действие
            'action' => $this->action,
            'action_display' => $this->getActionDisplay(),
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,

            // Данные
            'old_data' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->old_data
            ),
            'new_data' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->new_data
            ),
            'changed_fields' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->changed_fields
            ),
            'description' => $this->description,

            // Запрос
            'url' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->url
            ),
            'method' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->method
            ),
            'request_data' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->request_data
            ),

            // Контекст
            'context' => $this->context,
            'level' => $this->level,
            'level_color' => $this->getLevelColor(),
            'tags' => $this->tags,

            // Связи
            'related_user_id' => $this->related_user_id,
            'booking_id' => $this->booking_id,

            // Флаги
            'is_system' => $this->is_system,
            'is_api' => $this->is_api,
            'is_background' => $this->is_background,

            // Временные метки
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at,

            // Отношения
            'user' => new UserResource($this->whenLoaded('user')),
            'related_user' => new UserResource($this->whenLoaded('relatedUser')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'model' => $this->when($request->user() && $request->user()->isAdmin(),
                fn() => $this->getModel()
            ),

            // Форматированные данные для отображения
            'formatted_changes' => $this->getFormattedChanges(),
            'model_name' => $this->getModelName(),
        ];
    }

    private function getActionDisplay(): string
    {
        return match($this->action) {
            'create' => 'Создание',
            'update' => 'Обновление',
            'delete' => 'Удаление',
            'login' => 'Вход в систему',
            'login_failed' => 'Неудачная попытка входа',
            'logout' => 'Выход из системы',
            'password_change' => 'Смена пароля',
            'profile_update' => 'Обновление профиля',
            'booking_create' => 'Создание бронирования',
            'booking_update' => 'Обновление бронирования',
            'booking_cancel' => 'Отмена бронирования',
            'payment_create' => 'Создание платежа',
            'payment_update' => 'Обновление платежа',
            'review_create' => 'Создание отзыва',
            'review_update' => 'Обновление отзыва',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    private function getLevelColor(): string
    {
        return match($this->level) {
            'info' => 'primary',
            'warning' => 'warning',
            'error' => 'danger',
            'critical' => 'dark',
            default => 'secondary',
        };
    }

    private function getFormattedChanges(): ?array
    {
        if (!$this->changed_fields || !is_array($this->changed_fields)) {
            return null;
        }

        $formatted = [];
        foreach ($this->changed_fields as $field => $change) {
            $formatted[] = [
                'field' => $this->getFieldDisplayName($field),
                'old_value' => $this->formatValue($change['old'] ?? null),
                'new_value' => $this->formatValue($change['new'] ?? null),
            ];
        }

        return $formatted;
    }

    private function getFieldDisplayName(string $field): string
    {
        $fieldNames = [
            'name' => 'Имя',
            'email' => 'Email',
            'phone' => 'Телефон',
            'status' => 'Статус',
            'role' => 'Роль',
            'price_per_night' => 'Цена за ночь',
            'capacity' => 'Вместимость',
            'check_in' => 'Дата заезда',
            'check_out' => 'Дата выезда',
            'total_price' => 'Общая стоимость',
            'rating_overall' => 'Общий рейтинг',
            'payment_status' => 'Статус платежа',
            'booking_status' => 'Статус бронирования',
        ];

        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function formatValue($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    private function getModelName(): ?string
    {
        if (!$this->model_type) {
            return null;
        }

        $modelNames = [
            'App\Models\User' => 'Пользователь',
            'App\Models\Hotel' => 'Отель',
            'App\Models\Room' => 'Номер',
            'App\Models\Booking' => 'Бронирование',
            'App\Models\Payment' => 'Платеж',
            'App\Models\Review' => 'Отзыв',
            'App\Models\Notification' => 'Уведомление',
            'App\Models\ChatMessage' => 'Сообщение чата',
            'App\Models\Discount' => 'Скидка',
            'App\Models\BanList' => 'Бан',
            'App\Models\Setting' => 'Настройка',
            'App\Models\Page' => 'Страница',
            'App\Models\FAQ' => 'FAQ',
            'App\Models\Report' => 'Отчет',
            'App\Models\Backup' => 'Бекап',
            'App\Models\ReviewReport' => 'Жалоба на отзыв',
        ];

        return $modelNames[$this->model_type] ?? class_basename($this->model_type);
    }
}
