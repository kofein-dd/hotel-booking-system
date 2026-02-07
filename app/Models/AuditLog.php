<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_number',
        'user_id',
        'user_ip',
        'user_agent',
        'action',
        'model_type',
        'model_id',
        'old_data',
        'new_data',
        'changed_fields',
        'description',
        'url',
        'method',
        'request_data',
        'context',
        'level',
        'tags',
        'related_user_id',
        'booking_id',
        'is_system',
        'is_api',
        'is_background',
        'metadata',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'changed_fields' => 'array',
        'request_data' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'is_system' => 'boolean',
        'is_api' => 'boolean',
        'is_background' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Генерация номера лога
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (!$log->log_number) {
                $log->log_number = 'LOG-' . date('YmdHis') . '-' . strtoupper(uniqid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // Получить модель, к которой относится лог
    public function getModel()
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        try {
            return $this->model_type::find($this->model_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    // Записать действие
    public static function log(
        string $action,
               $model = null,
        User $user = null,
        array $oldData = null,
        array $newData = null,
        string $description = null,
        array $context = []
    ): self {
        $data = [
            'action' => $action,
            'description' => $description,
            'user_id' => $user?->id,
            'user_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'request_data' => request()->except(['password', 'token', 'api_token']),
            'context' => $context['context'] ?? null,
            'level' => $context['level'] ?? 'info',
            'tags' => $context['tags'] ?? [],
            'is_system' => $context['is_system'] ?? false,
            'is_api' => request()->is('api/*'),
            'is_background' => app()->runningInConsole(),
            'metadata' => $context['metadata'] ?? [],
        ];

        if ($model) {
            $data['model_type'] = get_class($model);
            $data['model_id'] = $model->id;

            // Определяем измененные поля
            if ($oldData && $newData) {
                $changed = [];
                foreach ($newData as $key => $value) {
                    if (!array_key_exists($key, $oldData) || $oldData[$key] != $value) {
                        $changed[$key] = [
                            'old' => $oldData[$key] ?? null,
                            'new' => $value,
                        ];
                    }
                }
                $data['changed_fields'] = $changed;
            }

            $data['old_data'] = $oldData;
            $data['new_data'] = $newData;
        }

        // Связи
        if (isset($context['related_user_id'])) {
            $data['related_user_id'] = $context['related_user_id'];
        }

        if (isset($context['booking_id'])) {
            $data['booking_id'] = $context['booking_id'];
        }

        return self::create($data);
    }

    // Записать действие создания
    public static function logCreate($model, User $user = null, string $description = null): self
    {
        return self::log('create', $model, $user, null, $model->toArray(), $description, [
            'context' => 'Создание записи',
        ]);
    }

    // Записать действие обновления
    public static function logUpdate($model, User $user = null, array $oldData, string $description = null): self
    {
        return self::log('update', $model, $user, $oldData, $model->toArray(), $description, [
            'context' => 'Обновление записи',
        ]);
    }

    // Записать действие удаления
    public static function logDelete($model, User $user = null, string $description = null): self
    {
        return self::log('delete', $model, $user, $model->toArray(), null, $description, [
            'context' => 'Удаление записи',
        ]);
    }

    // Записать действие входа
    public static function logLogin(User $user, bool $success = true, string $ip = null): self
    {
        return self::log($success ? 'login' : 'login_failed', null, $user, null, null,
            $success ? 'Успешный вход в систему' : 'Неудачная попытка входа', [
                'context' => 'Аутентификация',
                'level' => $success ? 'info' : 'warning',
                'user_ip' => $ip ?? request()->ip(),
            ]);
    }

    // Получить логи по модели
    public static function getForModel($model, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // Получить логи пользователя
    public static function getForUser(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $user->id)
            ->orWhere('related_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // Очистить старые логи
    public static function cleanOldLogs(int $days = 90): int
    {
        return self::where('created_at', '<', now()->subDays($days))
            ->where('level', '!=', 'critical')
            ->delete();
    }

    // Получить статистику логов
    public static function getStatistics(\Carbon\Carbon $from = null, \Carbon\Carbon $to = null): array
    {
        $query = self::query();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return [
            'total' => $query->count(),
            'by_action' => $query->groupBy('action')
                ->selectRaw('action, count(*) as count')
                ->pluck('count', 'action')
                ->toArray(),
            'by_level' => $query->groupBy('level')
                ->selectRaw('level, count(*) as count')
                ->pluck('count', 'level')
                ->toArray(),
            'by_user' => $query->whereNotNull('user_id')
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->with('user')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->user->name ?? 'Unknown' => $item->count];
                })
                ->toArray(),
        ];
    }
}
