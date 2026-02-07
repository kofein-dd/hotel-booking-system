<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

// Вебхуки для платежных систем
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe']);
    Route::post('/yookassa', [WebhookController::class, 'yookassa']);
    Route::post('/telegram', [WebhookController::class, 'telegram']);
});

// CRON задачи (защищенные ключом)
Route::prefix('cron')->middleware('cron.token')->group(function () {
    Route::get('/notify-upcoming-bookings', [CronController::class, 'notifyUpcomingBookings']);
    Route::get('/clean-expired-bookings', [CronController::class, 'cleanExpiredBookings']);
    Route::get('/generate-nightly-report', [CronController::class, 'generateNightlyReport']);
    Route::get('/backup-database', [CronController::class, 'backupDatabase']);
});
