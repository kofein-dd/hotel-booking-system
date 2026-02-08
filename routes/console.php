<?php

use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\CronController;

// Очистка старых токенов каждый день
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Очистка старых сессий
Schedule::command('session:gc')->daily();

// Очистка кэша раз в неделю
Schedule::command('cache:clear')->weekly();

// Создание резервных копий базы данных
Schedule::command('backup:run')->dailyAt('02:00');

// Отправка напоминаний о бронированиях
Schedule::call([CronController::class, 'sendBookingReminders'])
    ->dailyAt('09:00');

// Отмена просроченных бронирований
Schedule::call([CronController::class, 'cancelExpiredBookings'])
    ->dailyAt('03:00');

// Очистка старых уведомлений
Schedule::call([CronController::class, 'cleanupOldNotifications'])
    ->weekly();

// Сбор статистики
Schedule::call([CronController::class, 'collectStatistics'])
    ->dailyAt('23:00');
