<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_number',
        'name',
        'slug',
        'type',
        'category',
        'parameters',
        'filters',
        'date_from',
        'date_to',
        'status',
        'data',
        'summary',
        'charts',
        'file_path',
        'file_format',
        'file_size',
        'generated_at',
        'generation_time',
        'created_by',
        'generated_by',
        'is_scheduled',
        'schedule_cron',
        'last_scheduled_run',
        'next_scheduled_run',
        'is_auto_generate',
        'send_email',
        'email_recipients',
        'notification_settings',
        'metadata',
    ];

    protected $casts = [
        'parameters' => 'array',
        'filters' => 'array',
        'data' => 'array',
        'summary' => 'array',
        'charts' => 'array',
        'email_recipients' => 'array',
        'notification_settings' => 'array',
        'metadata' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
        'generated_at' => 'datetime',
        'last_scheduled_run' => 'datetime',
        'next_scheduled_run' => 'datetime',
        'is_scheduled' => 'boolean',
        'is_auto_generate' => 'boolean',
        'send_email' => 'boolean',
    ];

    // Генерация номера отчета
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (!$report->report_number) {
                $report->report_number = 'REP-' . date('Ymd') . '-' . strtoupper(uniqid());
            }

            if (!$report->slug) {
                $report->slug = \Illuminate\Support\Str::slug($report->name) . '-' . time();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // Проверка, завершен ли отчет
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    // Проверка, в обработке ли отчет
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    // Проверка, не удался ли отчет
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // Начать генерацию отчета
    public function startProcessing(User $generator = null): void
    {
        $this->status = 'processing';
        if ($generator) {
            $this->generated_by = $generator->id;
        }
        $this->save();
    }

    // Завершить генерацию отчета
    public function complete(array $data, array $summary = null, array $charts = null): void
    {
        $this->status = 'completed';
        $this->generated_at = now();
        $this->generation_time = now()->diffInSeconds($this->created_at);
        $this->data = $data;
        $this->summary = $summary;
        $this->charts = $charts;
        $this->save();
    }

    // Отметить отчет как неудачный
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->status = 'failed';
        $this->generated_at = now();

        if ($errorMessage) {
            $metadata = $this->metadata ?? [];
            $metadata['error'] = $errorMessage;
            $this->metadata = $metadata;
        }

        $this->save();
    }

    // Сохранить файл отчета
    public function saveFile(string $path, string $format, int $size): void
    {
        $this->file_path = $path;
        $this->file_format = $format;
        $this->file_size = $size;
        $this->save();
    }

    // Получить URL для скачивания файла
    public function getDownloadUrl(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return asset('storage/reports/' . basename($this->file_path));
    }

    // Проверка, есть ли файл отчета
    public function hasFile(): bool
    {
        return !empty($this->file_path) && file_exists(storage_path('app/' . $this->file_path));
    }

    // Получить период отчета в текстовом виде
    public function getPeriodText(): string
    {
        if ($this->date_from && $this->date_to) {
            return $this->date_from->format('d.m.Y') . ' - ' . $this->date_to->format('d.m.Y');
        }

        return match($this->category) {
            'daily' => 'За день: ' . now()->format('d.m.Y'),
            'weekly' => 'За неделю: ' . now()->startOfWeek()->format('d.m.Y') . ' - ' . now()->endOfWeek()->format('d.m.Y'),
            'monthly' => 'За месяц: ' . now()->format('F Y'),
            'quarterly' => 'За квартал: ' . now()->quarter . ' квартал ' . now()->format('Y'),
            'yearly' => 'За год: ' . now()->format('Y'),
            default => 'По требованию',
        };
    }

    // Рассчитать следующий запуск по расписанию
    public function calculateNextRun(): void
    {
        if (!$this->is_scheduled || !$this->schedule_cron) {
            return;
        }

        try {
            $cron = \Cron\CronExpression::factory($this->schedule_cron);
            $this->next_scheduled_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');
            $this->save();
        } catch (\Exception $e) {
            // Ошибка в cron выражении
        }
    }

    // Запустить по расписанию
    public function runScheduled(): void
    {
        if (!$this->is_scheduled || !$this->is_auto_generate) {
            return;
        }

        $this->last_scheduled_run = now();
        $this->calculateNextRun();
        $this->save();

        // Триггерим событие генерации отчета
        event(new \App\Events\ReportScheduled($this));
    }

    // Получить отчеты, готовые к запуску по расписанию
    public static function getScheduledReports(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_scheduled', true)
            ->where('is_auto_generate', true)
            ->where('status', '!=', 'processing')
            ->where(function ($query) {
                $query->whereNull('next_scheduled_run')
                    ->orWhere('next_scheduled_run', '<=', now());
            })
            ->get();
    }

    // Создать стандартный финансовый отчет
    public static function createFinancialReport(
        User $creator,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $filters = []
    ): self {
        return self::create([
            'name' => 'Финансовый отчет',
            'type' => 'financial',
            'category' => 'adhoc',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'parameters' => [
                'include_taxes' => true,
                'include_refunds' => true,
                'group_by' => 'day',
            ],
            'filters' => $filters,
            'created_by' => $creator->id,
            'is_auto_generate' => false,
        ]);
    }

    // Создать отчет по заполняемости
    public static function createOccupancyReport(
        User $creator,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $roomTypes = []
    ): self {
        return self::create([
            'name' => 'Отчет по заполняемости',
            'type' => 'occupancy',
            'category' => 'monthly',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'parameters' => [
                'room_types' => $roomTypes,
                'calculate_adr' => true,
                'calculate_revpar' => true,
            ],
            'created_by' => $creator->id,
            'is_auto_generate' => true,
        ]);
    }

    // Получить популярные отчеты по типу
    public static function getPopularReports(string $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('status', 'completed');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('generated_at', 'desc')
            ->limit(10)
            ->get();
    }

    // Архивировать старые отчеты
    public static function archiveOldReports(int $days = 30): int
    {
        return self::where('created_at', '<', now()->subDays($days))
            ->where('status', 'completed')
            ->update(['status' => 'archived']);
    }

    // Получить статистику по отчетам
    public static function getStatistics(): array
    {
        return [
            'total' => self::count(),
            'completed' => self::where('status', 'completed')->count(),
            'processing' => self::where('status', 'processing')->count(),
            'scheduled' => self::where('is_scheduled', true)->count(),
            'by_type' => self::groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }
}
