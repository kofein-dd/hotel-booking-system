<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Backup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'backup_number',
        'type',
        'purpose',
        'filename',
        'path',
        'storage_disk',
        'size',
        'database_name',
        'tables_count',
        'files_count',
        'included_directories',
        'excluded_directories',
        'status',
        'started_at',
        'completed_at',
        'duration',
        'created_by',
        'checksum',
        'is_verified',
        'verified_at',
        'verified_by',
        'is_scheduled',
        'schedule_cron',
        'retention_days',
        'expires_at',
        'logs',
        'error_message',
        'statistics',
        'metadata',
    ];

    protected $casts = [
        'included_directories' => 'array',
        'excluded_directories' => 'array',
        'logs' => 'array',
        'statistics' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_scheduled' => 'boolean',
        'size' => 'integer',
        'tables_count' => 'integer',
        'files_count' => 'integer',
        'duration' => 'integer',
        'retention_days' => 'integer',
    ];

    // Генерация номера бекапа
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($backup) {
            if (!$backup->backup_number) {
                $backup->backup_number = 'BACKUP-' . date('YmdHis') . '-' . strtoupper(uniqid());
            }

            if (!$backup->expires_at && $backup->retention_days) {
                $backup->expires_at = now()->addDays($backup->retention_days);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Начать создание бекапа
    public function start(): void
    {
        $this->status = 'processing';
        $this->started_at = now();
        $this->save();
    }

    // Завершить создание бекапа
    public function complete(array $statistics = []): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->duration = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;
        $this->statistics = $statistics;
        $this->save();
    }

    // Отметить бекап как неудачный
    public function markAsFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->completed_at = now();
        $this->error_message = $errorMessage;

        if ($this->started_at) {
            $this->duration = now()->diffInSeconds($this->started_at);
        }

        $this->save();
    }

    // Проверить бекап
    public function verify(User $verifier = null): bool
    {
        try {
            // Проверка существования файла
            if (!\Storage::disk($this->storage_disk)->exists($this->path)) {
                throw new \Exception('Файл бекапа не найден');
            }

            // Проверка контрольной суммы
            if ($this->checksum) {
                $fileChecksum = md5_file(\Storage::disk($this->storage_disk)->path($this->path));
                if ($fileChecksum !== $this->checksum) {
                    throw new \Exception('Контрольная сумма не совпадает');
                }
            }

            $this->is_verified = true;
            $this->verified_at = now();
            $this->verified_by = $verifier?->id;
            $this->status = 'verified';
            $this->save();

            return true;
        } catch (\Exception $e) {
            $this->status = 'corrupted';
            $this->error_message = $e->getMessage();
            $this->save();

            return false;
        }
    }

    // Получить URL для скачивания
    public function getDownloadUrl(): ?string
    {
        if (!\Storage::disk($this->storage_disk)->exists($this->path)) {
            return null;
        }

        return \Storage::disk($this->storage_disk)->url($this->path);
    }

    // Получить размер в читаемом формате
    public function getSizeFormatted(): string
    {
        if (!$this->size) {
            return '0 Б';
        }

        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    // Получить длительность в читаемом формате
    public function getDurationFormatted(): string
    {
        if (!$this->duration) {
            return '0 сек';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%d ч %d мин %d сек', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%d мин %d сек', $minutes, $seconds);
        } else {
            return sprintf('%d сек', $seconds);
        }
    }

    // Проверить, истек ли срок хранения
    public function isExpired(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at);
    }

    // Удалить файл бекапа
    public function deleteFile(): bool
    {
        try {
            if (\Storage::disk($this->storage_disk)->exists($this->path)) {
                return \Storage::disk($this->storage_disk)->delete($this->path);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Очистить старые бекапы
    public static function cleanOldBackups(): int
    {
        $expired = self::where('expires_at', '<', now())
            ->where('status', '!=', 'processing')
            ->get();

        $deleted = 0;

        foreach ($expired as $backup) {
            $backup->deleteFile();
            $backup->delete();
            $deleted++;
        }

        return $deleted;
    }

    // Создать запись о бекапе
    public static function createBackupRecord(
        string $type,
        string $filename,
        string $path,
        User $creator = null,
        array $options = []
    ): self {
        return self::create([
            'type' => $type,
            'purpose' => $options['purpose'] ?? 'manual',
            'filename' => $filename,
            'path' => $path,
            'storage_disk' => $options['storage_disk'] ?? 'local',
            'database_name' => $options['database_name'] ?? config('database.connections.mysql.database'),
            'created_by' => $creator?->id,
            'is_scheduled' => $options['is_scheduled'] ?? false,
            'schedule_cron' => $options['schedule_cron'] ?? null,
            'retention_days' => $options['retention_days'] ?? 30,
            'included_directories' => $options['included_directories'] ?? ['app', 'config', 'database', 'public'],
            'excluded_directories' => $options['excluded_directories'] ?? ['node_modules', 'vendor', 'storage/logs'],
        ]);
    }

    // Получить статистику бекапов
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'completed' => self::where('status', 'completed')->count(),
            'verified' => self::where('is_verified', true)->count(),
            'failed' => self::where('status', 'failed')->count(),
            'expired' => self::where('expires_at', '<', now())->count(),
            'total_size' => self::sum('size'),
            'by_type' => self::groupBy('type')
                ->selectRaw('type, count(*) as count, sum(size) as total_size')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->type => [
                        'count' => $item->count,
                        'total_size' => $item->total_size,
                    ]];
                })
                ->toArray(),
        ];
    }
}
