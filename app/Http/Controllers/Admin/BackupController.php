<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupRequest;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    protected $backupService;
    protected $backupPath;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
        $this->backupPath = storage_path('app/backups');
    }

    /**
     * Главная страница управления резервными копиями
     *
     * @return View
     */
    public function index(): View
    {
        $backups = $this->getBackupList();
        $diskSpace = $this->getDiskSpaceInfo();
        $settings = $this->getBackupSettings();

        $backupStats = [
            'total' => count($backups),
            'total_size' => $this->formatBytes($backups->sum('size')),
            'database_backups' => $backups->where('type', 'database')->count(),
            'files_backups' => $backups->where('type', 'files')->count(),
            'full_backups' => $backups->where('type', 'full')->count(),
            'oldest' => $backups->isNotEmpty() ? $backups->last()['created_at'] : null,
            'newest' => $backups->isNotEmpty() ? $backups->first()['created_at'] : null,
        ];

        return view('admin.backups.index', compact(
            'backups',
            'diskSpace',
            'settings',
            'backupStats'
        ));
    }

    /**
     * Создание новой резервной копии
     *
     * @param BackupRequest $request
     * @return JsonResponse
     */
    public function create(BackupRequest $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'database');
            $comment = $request->input('comment', '');

            $result = $this->backupService->createBackup($type, $comment);

            // Логируем создание резервной копии
            \App\Models\BackupLog::create([
                'type' => $type,
                'filename' => $result['filename'],
                'size' => $result['size'],
                'status' => 'success',
                'comment' => $comment,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Резервная копия успешно создана',
                'backup' => $result
            ]);

        } catch (\Exception $e) {
            \App\Models\BackupLog::create([
                'type' => $request->input('type', 'database'),
                'filename' => '',
                'size' => 0,
                'status' => 'failed',
                'comment' => $request->input('comment', '') . ' | Ошибка: ' . $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании резервной копии: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Загрузка резервной копии
     *
     * @param string $filename
     * @return StreamedResponse
     */
    public function download(string $filename): StreamedResponse
    {
        $filePath = $this->backupPath . '/' . $filename;

        if (!File::exists($filePath)) {
            abort(404, 'Резервная копия не найдена');
        }

        // Логируем скачивание
        \App\Models\BackupLog::create([
            'type' => $this->getBackupType($filename),
            'filename' => $filename,
            'size' => File::size($filePath),
            'status' => 'downloaded',
            'comment' => 'Скачано пользователем',
            'user_id' => auth()->id()
        ]);

        return Storage::disk('local')->download('backups/' . $filename);
    }

    /**
     * Восстановление из резервной копии
     *
     * @param Request $request
     * @param string $filename
     * @return JsonResponse
     */
    public function restore(Request $request, string $filename): JsonResponse
    {
        $request->validate([
            'confirmation' => 'required|in:yes,confirm',
            'type' => 'required|in:database,files,full'
        ]);

        if ($request->input('confirmation') !== 'confirm') {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо подтверждение для восстановления'
            ], 400);
        }

        try {
            $filePath = $this->backupPath . '/' . $filename;

            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Резервная копия не найдена'
                ], 404);
            }

            $type = $request->input('type');
            $result = $this->backupService->restoreBackup($filePath, $type);

            // Логируем восстановление
            \App\Models\BackupLog::create([
                'type' => $type,
                'filename' => $filename,
                'size' => File::size($filePath),
                'status' => 'restored',
                'comment' => 'Восстановление системы',
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Восстановление успешно завершено',
                'details' => $result
            ]);

        } catch (\Exception $e) {
            \App\Models\BackupLog::create([
                'type' => $request->input('type'),
                'filename' => $filename,
                'size' => 0,
                'status' => 'restore_failed',
                'comment' => 'Ошибка восстановления: ' . $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при восстановлении: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаление резервной копии
     *
     * @param Request $request
     * @param string $filename
     * @return JsonResponse
     */
    public function destroy(Request $request, string $filename): JsonResponse
    {
        try {
            $filePath = $this->backupPath . '/' . $filename;

            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Резервная копия не найдена'
                ], 404);
            }

            // Получаем информацию о файле перед удалением
            $fileInfo = [
                'type' => $this->getBackupType($filename),
                'size' => File::size($filePath),
                'created_at' => File::lastModified($filePath)
            ];

            // Удаляем файл
            File::delete($filePath);

            // Логируем удаление
            \App\Models\BackupLog::create([
                'type' => $fileInfo['type'],
                'filename' => $filename,
                'size' => $fileInfo['size'],
                'status' => 'deleted',
                'comment' => 'Удалено пользователем',
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Резервная копия успешно удалена',
                'freed_space' => $this->formatBytes($fileInfo['size'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Массовое удаление старых резервных копий
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cleanup(Request $request): JsonResponse
    {
        $request->validate([
            'keep_days' => 'required|integer|min:1|max:365',
            'keep_count' => 'required|integer|min:1|max:100',
            'type' => 'nullable|in:database,files,full,all'
        ]);

        try {
            $keepDays = $request->input('keep_days');
            $keepCount = $request->input('keep_count');
            $type = $request->input('type', 'all');

            $result = $this->backupService->cleanupBackups($keepDays, $keepCount, $type);

            return response()->json([
                'success' => true,
                'message' => 'Очистка завершена',
                'deleted' => $result['deleted'],
                'freed_space' => $this->formatBytes($result['freed_space']),
                'remaining' => $result['remaining']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при очистке: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Настройки автоматического резервного копирования
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function settings(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'auto_backup' => 'boolean',
                'backup_schedule' => 'required_if:auto_backup,1|in:daily,weekly,monthly',
                'backup_time' => 'required_if:auto_backup,1|date_format:H:i',
                'backup_type' => 'required_if:auto_backup,1|in:database,files,full',
                'keep_backups_days' => 'required|integer|min:1|max:365',
                'keep_backups_count' => 'required|integer|min:1|max:100',
                'notify_on_backup' => 'boolean',
                'notify_email' => 'nullable|email',
                'compress_backups' => 'boolean',
                'max_backup_size' => 'nullable|integer|min:100|max:10240' // в МБ
            ]);

            $settings = $request->only([
                'auto_backup', 'backup_schedule', 'backup_time', 'backup_type',
                'keep_backups_days', 'keep_backups_count', 'notify_on_backup',
                'notify_email', 'compress_backups', 'max_backup_size'
            ]);

            // Сохраняем настройки
            foreach ($settings as $key => $value) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => 'backup_' . $key],
                    ['value' => $value, 'category' => 'backup']
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Настройки сохранены'
            ]);
        }

        // GET запрос - возвращаем текущие настройки
        $settings = $this->getBackupSettings();

        return response()->json($settings);
    }

    /**
     * Проверка состояния резервных копий
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $backups = $this->getBackupList();
        $diskSpace = $this->getDiskSpaceInfo();
        $settings = $this->getBackupSettings();

        $lastBackupLog = \App\Models\BackupLog::latest()->first();
        $lastSuccessBackup = \App\Models\BackupLog::where('status', 'success')
            ->latest()
            ->first();

        $status = 'unknown';
        $issues = [];

        // Проверяем наличие свежих резервных копий
        if ($lastSuccessBackup) {
            $hoursSinceLastBackup = now()->diffInHours($lastSuccessBackup->created_at);

            if ($hoursSinceLastBackup > 24 && $settings['auto_backup']) {
                $status = 'warning';
                $issues[] = 'Последняя резервная копия была создана более 24 часов назад';
            } else {
                $status = 'healthy';
            }
        } else {
            $status = 'warning';
            $issues[] = 'Нет успешных резервных копий';
        }

        // Проверяем свободное место на диске
        if ($diskSpace['free_percent'] < 10) {
            $status = 'critical';
            $issues[] = 'Мало свободного места на диске: ' . $diskSpace['free_percent'] . '%';
        } elseif ($diskSpace['free_percent'] < 20) {
            $status = $status === 'healthy' ? 'warning' : $status;
            $issues[] = 'Свободного места на диске осталось мало: ' . $diskSpace['free_percent'] . '%';
        }

        // Проверяем количество резервных копий
        if ($backups->isEmpty()) {
            $status = 'critical';
            $issues[] = 'Нет резервных копий';
        }

        return response()->json([
            'status' => $status,
            'issues' => $issues,
            'last_backup' => $lastSuccessBackup,
            'backup_count' => $backups->count(),
            'total_backup_size' => $this->formatBytes($backups->sum('size')),
            'disk_space' => $diskSpace,
            'settings' => $settings
        ]);
    }

    /**
     * Логи резервного копирования
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function logs(Request $request)
    {
        $query = \App\Models\BackupLog::with('user')
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(50);

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        $types = \App\Models\BackupLog::distinct()->pluck('type');
        $statuses = \App\Models\BackupLog::distinct()->pluck('status');

        return view('admin.backups.logs', compact('logs', 'types', 'statuses'));
    }

    /**
     * Получить список резервных копий
     *
     * @return \Illuminate\Support\Collection
     */
    private function getBackupList(): \Illuminate\Support\Collection
    {
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
            return collect();
        }

        $files = File::files($this->backupPath);

        $backups = collect($files)->map(function ($file) {
            $filename = $file->getFilename();

            return [
                'filename' => $filename,
                'type' => $this->getBackupType($filename),
                'size' => $file->getSize(),
                'size_formatted' => $this->formatBytes($file->getSize()),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
                'created_at_formatted' => Carbon::createFromTimestamp($file->getMTime())->format('d.m.Y H:i'),
                'extension' => $file->getExtension(),
                'path' => $file->getPathname()
            ];
        })->sortByDesc('created_at');

        return $backups;
    }

    /**
     * Определить тип резервной копии по имени файла
     *
     * @param string $filename
     * @return string
     */
    private function getBackupType(string $filename): string
    {
        if (str_contains($filename, 'database')) {
            return 'database';
        } elseif (str_contains($filename, 'files')) {
            return 'files';
        } elseif (str_contains($filename, 'full')) {
            return 'full';
        }

        return 'unknown';
    }

    /**
     * Получить информацию о дисковом пространстве
     *
     * @return array
     */
    private function getDiskSpaceInfo(): array
    {
        $totalSpace = disk_total_space($this->backupPath);
        $freeSpace = disk_free_space($this->backupPath);
        $usedSpace = $totalSpace - $freeSpace;

        return [
            'total' => $this->formatBytes($totalSpace),
            'free' => $this->formatBytes($freeSpace),
            'used' => $this->formatBytes($usedSpace),
            'total_bytes' => $totalSpace,
            'free_bytes' => $freeSpace,
            'used_bytes' => $usedSpace,
            'used_percent' => round(($usedSpace / $totalSpace) * 100, 2),
            'free_percent' => round(($freeSpace / $totalSpace) * 100, 2)
        ];
    }

    /**
     * Получить настройки резервного копирования
     *
     * @return array
     */
    private function getBackupSettings(): array
    {
        $settings = \App\Models\Setting::where('key', 'like', 'backup_%')
            ->pluck('value', 'key')
            ->mapWithKeys(function ($value, $key) {
                return [str_replace('backup_', '', $key) => $value];
            })
            ->toArray();

        // Значения по умолчанию
        $defaults = [
            'auto_backup' => false,
            'backup_schedule' => 'daily',
            'backup_time' => '02:00',
            'backup_type' => 'database',
            'keep_backups_days' => 30,
            'keep_backups_count' => 10,
            'notify_on_backup' => true,
            'notify_email' => config('mail.from.address'),
            'compress_backups' => true,
            'max_backup_size' => 1024 // 1GB
        ];

        return array_merge($defaults, $settings);
    }

    /**
     * Форматирование размера в байтах
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Запустить автоматическое резервное копирование (для CRON)
     *
     * @return JsonResponse
     */
    public function runAutoBackup(): JsonResponse
    {
        try {
            $settings = $this->getBackupSettings();

            if (!$settings['auto_backup']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Автоматическое резервное копирование отключено'
                ]);
            }

            // Проверяем, нужно ли запускать резервное копирование по расписанию
            $lastBackup = \App\Models\BackupLog::where('status', 'success')
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($lastBackup && $settings['backup_schedule'] === 'daily') {
                return response()->json([
                    'success' => false,
                    'message' => 'Резервное копирование уже выполнено сегодня'
                ]);
            }

            $result = $this->backupService->createBackup(
                $settings['backup_type'],
                'Автоматическое резервное копирование'
            );

            // Отправляем уведомление, если включено
            if ($settings['notify_on_backup'] && !empty($settings['notify_email'])) {
                $this->sendBackupNotification($result, $settings['notify_email']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Автоматическое резервное копирование выполнено',
                'backup' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка автоматического резервного копирования: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при автоматическом резервном копировании'
            ], 500);
        }
    }

    /**
     * Отправить уведомление о резервном копировании
     *
     * @param array $backupInfo
     * @param string $email
     * @return void
     */
    private function sendBackupNotification(array $backupInfo, string $email): void
    {
        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(
                new \App\Mail\BackupNotification($backupInfo)
            );
        } catch (\Exception $e) {
            \Log::error('Ошибка отправки уведомления о резервном копировании: ' . $e->getMessage());
        }
    }
}
