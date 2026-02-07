<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Кастомные команды
Artisan::command('booking:send-reminders', function () {
    $this->info('Sending booking reminders...');
    // Логика отправки напоминаний
})->purpose('Send booking reminders to users');

Artisan::command('booking:cleanup-expired', function () {
    $this->info('Cleaning up expired bookings...');
    // Логика очистки просроченных бронирований
})->purpose('Clean up expired bookings');

Artisan::command('backup:create', function () {
    $this->info('Creating database backup...');
    // Логика создания бэкапа
})->purpose('Create database backup');

Artisan::command('backup:restore {filename}', function ($filename) {
    $this->info("Restoring backup from {$filename}...");
    // Логика восстановления бэкапа
})->purpose('Restore database from backup');
