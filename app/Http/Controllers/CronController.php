<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Services\CleanupService;
use App\Services\ReportService;
use App\Services\SyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CronController extends Controller
{
    protected $bookingService;
    protected $notificationService;
    protected $cleanupService;
    protected $reportService;
    protected $syncService;

    public function __construct(
        BookingService $bookingService,
        NotificationService $notificationService,
        CleanupService $cleanupService,
        ReportService $reportService,
        SyncService $syncService
    ) {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
        $this->cleanupService = $cleanupService;
        $this->reportService = $reportService;
        $this->syncService = $syncService;

        // Базовая аутентификация для cron задач
        $this->middleware(function ($request, $next) {
            $cronToken = config('cron.token');
            $providedToken = $request->input('token') ?? $request->header('X-Cron-Token');

            if (!$cronToken || $providedToken !== $cronToken) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return $next($request);
        });
    }

    /**
     * Ежедневные задачи
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function daily(Request $request): JsonResponse
    {
        try {
            Log::info('Starting daily cron tasks');

            $results = [];

            // 1. Проверка статусов бронирований
            $results['booking_status'] = $this->updateBookingStatuses();

            // 2. Отправка напоминаний о предстоящих заездах
            $results['reminders'] = $this->sendCheckinReminders();

            // 3. Отправка напоминаний о необходимости оставить отзыв
            $results['review_reminders'] = $this->sendReviewReminders();

            // 4. Очистка неоплаченных бронирований
            $results['cleanup_unpaid'] = $this->cleanupUnpaidBookings();

            // 5. Генерация ежедневного отчета
            $results['daily_report'] = $this->generateDailyReport();

            // 6. Проверка доступности номеров
            $results['availability_check'] = $this->checkRoomAvailability();

            // 7. Синхронизация с внешними сервисами
            $results['sync'] = $this->runDailySync();

            Log::info('Daily cron tasks completed', ['results' => $results]);

            return response()->json([
                'success' => true,
                'message' => 'Daily tasks completed',
                'tasks' => $results,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Daily cron error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Еженедельные задачи
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function weekly(Request $request): JsonResponse
    {
        try {
            Log::info('Starting weekly cron tasks');

            $results = [];

            // 1. Генерация недельного отчета
            $results['weekly_report'] = $this->generateWeeklyReport();

            // 2. Очистка старых логов
            $results['cleanup_logs'] = $this->cleanupOldLogs();

            // 3. Резервное копирование (если настроено)
            $results['backup'] = $this->runWeeklyBackup();

            // 4. Статистика пользователей
            $results['user_stats'] = $this->updateUserStatistics();

            // 5. Отправка недельного дайджеста администраторам
            $results['admin_digest'] = $this->sendAdminWeeklyDigest();

            // 6. Проверка подписок
            $results['subscriptions'] = $this->checkSubscriptions();

            Log::info('Weekly cron tasks completed', ['results' => $results]);

            return response()->json([
                'success' => true,
                'message' => 'Weekly tasks completed',
                'tasks' => $results,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Weekly cron error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ежемесячные задачи
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function monthly(Request $request): JsonResponse
    {
        try {
            Log::info('Starting monthly cron tasks');

            $results = [];

            // 1. Генерация месячного отчета
            $results['monthly_report'] = $this->generateMonthlyReport();

            // 2. Архивация старых данных
            $results['archive'] = $this->archiveOldData();

            // 3. Очистка кэша
            $results['cache_cleanup'] = $this->cleanupCache();

            // 4. Обновление статистики за месяц
            $results['monthly_stats'] = $this->updateMonthlyStatistics();

            // 5. Отправка отчетов владельцам
            $results['owner_reports'] = $this->sendOwnerMonthlyReports();

            // 6. Проверка лицензий и обновлений
            $results['system_check'] = $this->performSystemCheck();

            Log::info('Monthly cron tasks completed', ['results' => $results]);

            return response()->json([
                'success' => true,
                'message' => 'Monthly tasks completed',
                'tasks' => $results,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Monthly cron error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Задачи каждый час
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function hourly(Request $request): JsonResponse
    {
        try {
            Log::info('Starting hourly cron tasks');

            $results = [];

            // 1. Проверка новых бронирований
            $results['new_bookings'] = $this->processNewBookings();

            // 2. Отправка мгновенных уведомлений
            $results['instant_notifications'] = $this->sendInstantNotifications();

            // 3. Синхронизация с платежными системами
            $results['payment_sync'] = $this->syncPaymentStatuses();

            // 4. Проверка очереди задач
            $results['queue_check'] = $this->checkQueueStatus();

            // 5. Мониторинг системы
            $results['system_monitor'] = $this->monitorSystemHealth();

            // 6. Обновление курсов валют
            $results['currency_rates'] = $this->updateCurrencyRates();

            Log::info('Hourly cron tasks completed', ['results' => $results]);

            return response()->json([
                'success' => true,
                'message' => 'Hourly tasks completed',
                'tasks' => $results,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Hourly cron error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Задачи каждые 5 минут
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function everyFiveMinutes(Request $request): JsonResponse
    {
        try {
            $results = [];

            // 1. Проверка новых сообщений в чате
            $results['chat_messages'] = $this->processChatMessages();

            // 2. Отправка email из очереди
            $results['email_queue'] = $this->processEmailQueue();

            // 3. Обновление онлайн статуса пользователей
            $results['online_status'] = $this->updateOnlineStatus();

            // 4. Проверка вебхуков
            $results['webhook_check'] = $this->checkPendingWebhooks();

            // 5. Кэширование горячих данных
            $results['cache_warmup'] = $this->warmupCache();

            return response()->json([
                'success' => true,
                'message' => '5-minute tasks completed',
                'tasks' => $results,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('5-minute cron error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновление статусов бронирований
     *
     * @return array
     */
    private function updateBookingStatuses(): array
    {
        $results = [
            'updated' => 0,
            'activated' => [],
            'completed' => [],
            'cancelled' => []
        ];

        try {
            // Активация бронирований (дата заезда наступила)
            $activated = $this->bookingService->activatePendingBookings();
            $results['activated'] = $activated;
            $results['updated'] += count($activated);

            // Завершение бронирований (дата выезда прошла)
            $completed = $this->bookingService->completeActiveBookings();
            $results['completed'] = $completed;
            $results['updated'] += count($completed);

            // Автоматическая отмена неоплаченных бронирований
            $cancelled = $this->bookingService->cancelUnpaidBookings();
            $results['cancelled'] = $cancelled;
            $results['updated'] += count($cancelled);

            // Обновление статусов по другим критериям
            $otherUpdates = $this->bookingService->updateOtherBookingStatuses();
            $results['updated'] += $otherUpdates;

        } catch (\Exception $e) {
            Log::error('Update booking statuses error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Отправка напоминаний о предстоящих заездах
     *
     * @return array
     */
    private function sendCheckinReminders(): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'bookings' => []
        ];

        try {
            // Напоминание за 3 дня
            $threeDayReminders = $this->notificationService->sendCheckinReminders(3);
            $results['sent'] += $threeDayReminders['sent'];
            $results['failed'] += $threeDayReminders['failed'];
            $results['bookings'] = array_merge($results['bookings'], $threeDayReminders['bookings']);

            // Напоминание за 1 день
            $oneDayReminders = $this->notificationService->sendCheckinReminders(1);
            $results['sent'] += $oneDayReminders['sent'];
            $results['failed'] += $oneDayReminders['failed'];
            $results['bookings'] = array_merge($results['bookings'], $oneDayReminders['bookings']);

            // Напоминание в день заезда
            $todayReminders = $this->notificationService->sendCheckinReminders(0);
            $results['sent'] += $todayReminders['sent'];
            $results['failed'] += $todayReminders['failed'];
            $results['bookings'] = array_merge($results['bookings'], $todayReminders['bookings']);

        } catch (\Exception $e) {
            Log::error('Send checkin reminders error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Отправка напоминаний об отзывах
     *
     * @return array
     */
    private function sendReviewReminders(): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0
        ];

        try {
            // Напоминание через 2 дня после выезда
            $reminders = $this->notificationService->sendReviewReminders(2);
            $results['sent'] = $reminders['sent'];
            $results['failed'] = $reminders['failed'];

        } catch (\Exception $e) {
            Log::error('Send review reminders error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Очистка неоплаченных бронирований
     *
     * @return array
     */
    private function cleanupUnpaidBookings(): array
    {
        $results = [
            'cleaned' => 0,
            'bookings' => []
        ];

        try {
            // Бронирования старше 24 часов без оплаты
            $cleaned = $this->cleanupService->cleanupUnpaidBookings(24);
            $results['cleaned'] = count($cleaned);
            $results['bookings'] = $cleaned;

        } catch (\Exception $e) {
            Log::error('Cleanup unpaid bookings error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Генерация ежедневного отчета
     *
     * @return array
     */
    private function generateDailyReport(): array
    {
        $results = [
            'generated' => false,
            'report_id' => null
        ];

        try {
            $report = $this->reportService->generateDailyReport();

            if ($report) {
                $results['generated'] = true;
                $results['report_id'] = $report['id'];
                $results['data'] = $report['data'];

                // Отправка отчета администраторам
                $sent = $this->notificationService->sendDailyReportToAdmins($report);
                $results['sent_to_admins'] = $sent;
            }

        } catch (\Exception $e) {
            Log::error('Generate daily report error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Проверка доступности номеров
     *
     * @return array
     */
    private function checkRoomAvailability(): array
    {
        $results = [
            'checked' => 0,
            'unavailable' => 0,
            'updated' => 0
        ];

        try {
            // Проверка номеров, которые должны быть недоступны
            $availabilityCheck = $this->bookingService->checkAndUpdateRoomAvailability();
            $results = array_merge($results, $availabilityCheck);

        } catch (\Exception $e) {
            Log::error('Check room availability error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Ежедневная синхронизация
     *
     * @return array
     */
    private function runDailySync(): array
    {
        $results = [
            'synced' => [],
            'failed' => []
        ];

        try {
            // Синхронизация с календарями (Google Calendar, Outlook)
            $calendarSync = $this->syncService->syncWithCalendars();
            $results['synced']['calendars'] = $calendarSync;

            // Синхронизация с CRM системами
            $crmSync = $this->syncService->syncWithCRM();
            $results['synced']['crm'] = $crmSync;

            // Синхронизация с каналами бронирования (Booking.com, Airbnb и т.д.)
            $channelSync = $this->syncService->syncWithChannels();
            $results['synced']['channels'] = $channelSync;

        } catch (\Exception $e) {
            Log::error('Daily sync error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Генерация недельного отчета
     *
     * @return array
     */
    private function generateWeeklyReport(): array
    {
        $results = [
            'generated' => false,
            'report_id' => null
        ];

        try {
            $report = $this->reportService->generateWeeklyReport();

            if ($report) {
                $results['generated'] = true;
                $results['report_id'] = $report['id'];
                $results['data'] = $report['data'];

                // Отправка отчета
                $sent = $this->notificationService->sendWeeklyReport($report);
                $results['sent'] = $sent;
            }

        } catch (\Exception $e) {
            Log::error('Generate weekly report error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Очистка старых логов
     *
     * @return array
     */
    private function cleanupOldLogs(): array
    {
        $results = [
            'cleaned' => 0,
            'tables' => []
        ];

        try {
            // Очистка логов старше 30 дней
            $cleaned = $this->cleanupService->cleanupOldLogs(30);
            $results['cleaned'] = $cleaned['total'];
            $results['tables'] = $cleaned['by_table'];

        } catch (\Exception $e) {
            Log::error('Cleanup old logs error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Еженедельное резервное копирование
     *
     * @return array
     */
    private function runWeeklyBackup(): array
    {
        $results = [
            'created' => false,
            'backup_id' => null
        ];

        try {
            // Проверяем, нужно ли выполнять резервное копирование
            if (config('backup.weekly_enabled', false)) {
                $backup = $this->cleanupService->createWeeklyBackup();

                if ($backup) {
                    $results['created'] = true;
                    $results['backup_id'] = $backup['id'];
                    $results['size'] = $backup['size'];
                    $results['path'] = $backup['path'];
                }
            } else {
                $results['skipped'] = 'Weekly backup disabled';
            }

        } catch (\Exception $e) {
            Log::error('Weekly backup error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Обновление статистики пользователей
     *
     * @return array
     */
    private function updateUserStatistics(): array
    {
        $results = [
            'updated' => 0,
            'users' => []
        ];

        try {
            $stats = $this->reportService->updateUserStatistics();
            $results['updated'] = $stats['updated'];
            $results['users'] = $stats['users'];

        } catch (\Exception $e) {
            Log::error('Update user statistics error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Отправка недельного дайджеста администраторам
     *
     * @return array
     */
    private function sendAdminWeeklyDigest(): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0
        ];

        try {
            $digest = $this->notificationService->sendAdminWeeklyDigest();
            $results['sent'] = $digest['sent'];
            $results['failed'] = $digest['failed'];

        } catch (\Exception $e) {
            Log::error('Send admin weekly digest error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Проверка подписок
     *
     * @return array
     */
    private function checkSubscriptions(): array
    {
        $results = [
            'checked' => 0,
            'expired' => 0,
            'renewed' => 0
        ];

        try {
            $subscriptions = $this->bookingService->checkSubscriptions();
            $results = array_merge($results, $subscriptions);

        } catch (\Exception $e) {
            Log::error('Check subscriptions error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Обработка новых бронирований
     *
     * @return array
     */
    private function processNewBookings(): array
    {
        $results = [
            'processed' => 0,
            'bookings' => []
        ];

        try {
            $bookings = $this->bookingService->processNewBookings();
            $results['processed'] = count($bookings);
            $results['bookings'] = $bookings;

        } catch (\Exception $e) {
            Log::error('Process new bookings error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Отправка мгновенных уведомлений
     *
     * @return array
     */
    private function sendInstantNotifications(): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0
        ];

        try {
            $notifications = $this->notificationService->sendInstantNotifications();
            $results['sent'] = $notifications['sent'];
            $results['failed'] = $notifications['failed'];

        } catch (\Exception $e) {
            Log::error('Send instant notifications error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Синхронизация статусов платежей
     *
     * @return array
     */
    private function syncPaymentStatuses(): array
    {
        $results = [
            'synced' => 0,
            'updated' => 0
        ];

        try {
            $sync = $this->syncService->syncPaymentStatuses();
            $results['synced'] = $sync['synced'];
            $results['updated'] = $sync['updated'];

        } catch (\Exception $e) {
            Log::error('Sync payment statuses error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Проверка статуса очереди
     *
     * @return array
     */
    private function checkQueueStatus(): array
    {
        $results = [
            'status' => 'unknown',
            'jobs' => 0
        ];

        try {
            $queueStatus = $this->cleanupService->checkQueueStatus();
            $results['status'] = $queueStatus['status'];
            $results['jobs'] = $queueStatus['pending_jobs'];

            // Если очередь переполнена, отправляем предупреждение
            if ($queueStatus['pending_jobs'] > 100) {
                $this->notificationService->sendQueueWarning($queueStatus['pending_jobs']);
                $results['warning_sent'] = true;
            }

        } catch (\Exception $e) {
            Log::error('Check queue status error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Мониторинг здоровья системы
     *
     * @return array
     */
    private function monitorSystemHealth(): array
    {
        $results = [
            'healthy' => true,
            'checks' => []
        ];

        try {
            $healthChecks = $this->cleanupService->performHealthChecks();

            foreach ($healthChecks as $check => $status) {
                $results['checks'][$check] = $status['healthy'];

                if (!$status['healthy']) {
                    $results['healthy'] = false;
                    $results['errors'][$check] = $status['message'];

                    // Отправка предупреждения
                    $this->notificationService->sendSystemAlert($check, $status['message']);
                }
            }

        } catch (\Exception $e) {
            Log::error('System health monitoring error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
            $results['healthy'] = false;
        }

        return $results;
    }

    /**
     * Обновление курсов валют
     *
     * @return array
     */
    private function updateCurrencyRates(): array
    {
        $results = [
            'updated' => false,
            'rates' => []
        ];

        try {
            $rates = $this->syncService->updateCurrencyRates();

            if ($rates) {
                $results['updated'] = true;
                $results['rates'] = $rates;
                $results['timestamp'] = now()->toISOString();
            }

        } catch (\Exception $e) {
            Log::error('Update currency rates error: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Запуск конкретной задачи вручную
     *
     * @param Request $request
     * @param string $task
     * @return JsonResponse
     */
    public function runTask(Request $request, string $task): JsonResponse
    {
        try {
            $methodName = 'run' . ucfirst(camel_case($task));

            if (!method_exists($this, $methodName)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Task not found'
                ], 404);
            }

            $result = $this->$methodName($request);

            return response()->json([
                'success' => true,
                'message' => "Task '{$task}' executed successfully",
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error("Manual task execution error ({$task}): " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статус выполнения задач
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $status = [
            'last_execution' => [
                'daily' => Cache::get('cron_last_daily', 'Never'),
                'hourly' => Cache::get('cron_last_hourly', 'Never'),
                'weekly' => Cache::get('cron_last_weekly', 'Never'),
                'monthly' => Cache::get('cron_last_monthly', 'Never')
            ],
            'next_scheduled' => [
                'daily' => now()->addDay()->startOfDay()->toISOString(),
                'hourly' => now()->addHour()->startOfHour()->toISOString(),
                'weekly' => now()->addWeek()->startOfWeek()->toISOString(),
                'monthly' => now()->addMonth()->startOfMonth()->toISOString()
            ],
            'system' => [
                'timezone' => config('app.timezone'),
                'environment' => config('app.env'),
                'maintenance_mode' => app()->isDownForMaintenance()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }
}
