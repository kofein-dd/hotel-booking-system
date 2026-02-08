<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\FAQController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BanListController;
use App\Http\Controllers\Admin\ReviewReportController;
use App\Http\Controllers\Admin\FAQSuggestionController;
use App\Http\Controllers\Admin\FacilityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // Главная панель администратора
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Пользователи
    Route::resource('users', UserController::class);
    Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::get('users/{user}/activity', [UserController::class, 'activity'])->name('users.activity');

    // Отели
    Route::resource('hotels', HotelController::class);
    Route::post('hotels/{hotel}/toggle-status', [HotelController::class, 'toggleStatus'])->name('hotels.toggle-status');
    Route::post('hotels/{hotel}/upload-images', [HotelController::class, 'uploadImages'])->name('hotels.upload-images');
    Route::delete('hotels/{hotel}/delete-image/{imageId}', [HotelController::class, 'deleteImage'])->name('hotels.delete-image');

    // Номера
    Route::resource('rooms', RoomController::class);
    Route::post('rooms/{room}/toggle-status', [RoomController::class, 'toggleStatus'])->name('rooms.toggle-status');
    Route::post('rooms/{room}/upload-images', [RoomController::class, 'uploadImages'])->name('rooms.upload-images');
    Route::delete('rooms/{room}/delete-image/{imageId}', [RoomController::class, 'deleteImage'])->name('rooms.delete-image');
    Route::get('rooms/{room}/availability-calendar', [RoomController::class, 'availabilityCalendar'])->name('rooms.availability-calendar');
    Route::post('rooms/{room}/block-dates', [RoomController::class, 'blockDates'])->name('rooms.block-dates');

    // Бронирования
    Route::resource('bookings', BookingController::class)->except(['create', 'store']);
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn'])->name('bookings.check-in');
    Route::post('bookings/{booking}/check-out', [BookingController::class, 'checkOut'])->name('bookings.check-out');
    Route::get('bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
    Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');

    // Платежи
    Route::resource('payments', PaymentController::class)->except(['create', 'store', 'edit', 'update']);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markAsPaid'])->name('payments.mark-paid');

    // Отзывы
    Route::resource('reviews', ReviewController::class)->except(['create', 'store']);
    Route::post('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
    Route::post('reviews/{review}/feature', [ReviewController::class, 'toggleFeatured'])->name('reviews.toggle-featured');

    // Жалобы на отзывы
    Route::resource('review-reports', ReviewReportController::class)->except(['create', 'store']);
    Route::post('review-reports/{report}/resolve', [ReviewReportController::class, 'resolve'])->name('review-reports.resolve');
    Route::post('review-reports/{report}/dismiss', [ReviewReportController::class, 'dismiss'])->name('review-reports.dismiss');

    // Уведомления
    Route::resource('notifications', NotificationController::class);
    Route::post('notifications/send-bulk', [NotificationController::class, 'sendBulk'])->name('notifications.send-bulk');
    Route::post('notifications/{notification}/send', [NotificationController::class, 'send'])->name('notifications.send');
    Route::get('notifications/templates', [NotificationController::class, 'templates'])->name('notifications.templates');

    // Чат
    Route::resource('chat', ChatController::class)->only(['index', 'show']);
    Route::post('chat/{session}/message', [ChatController::class, 'sendMessage'])->name('chat.send-message');
    Route::put('chat/{session}/close', [ChatController::class, 'closeSession'])->name('chat.close');
    Route::put('chat/{session}/assign', [ChatController::class, 'assignToAdmin'])->name('chat.assign');
    Route::get('chat/unassigned', [ChatController::class, 'unassigned'])->name('chat.unassigned');

    // Скидки и промокоды
    Route::resource('discounts', DiscountController::class);
    Route::post('discounts/{discount}/toggle-status', [DiscountController::class, 'toggleStatus'])->name('discounts.toggle-status');
    Route::get('discounts/{discount}/usage', [DiscountController::class, 'usage'])->name('discounts.usage');

    // Удобства и услуги
    Route::resource('facilities', FacilityController::class);
    Route::post('facilities/reorder', [FacilityController::class, 'reorder'])->name('facilities.reorder');

    // Статистика
    Route::prefix('statistics')->name('statistics.')->group(function () {
        Route::get('/', [StatisticsController::class, 'index'])->name('index');
        Route::get('/bookings', [StatisticsController::class, 'bookings'])->name('bookings');
        Route::get('/revenue', [StatisticsController::class, 'revenue'])->name('revenue');
        Route::get('/users', [StatisticsController::class, 'users'])->name('users');
        Route::get('/reviews', [StatisticsController::class, 'reviews'])->name('reviews');
        Route::get('/export/{type}', [StatisticsController::class, 'export'])->name('export');
    });

    // Настройки
    Route::resource('settings', SettingController::class)->only(['index', 'update']);
    Route::post('settings/update-multiple', [SettingController::class, 'updateMultiple'])->name('settings.update-multiple');
    Route::get('settings/system', [SettingController::class, 'system'])->name('settings.system');
    Route::post('settings/system', [SettingController::class, 'updateSystem']);
    Route::get('settings/payment', [SettingController::class, 'payment'])->name('settings.payment');
    Route::post('settings/payment', [SettingController::class, 'updatePayment']);
    Route::get('settings/notification', [SettingController::class, 'notification'])->name('settings.notification');
    Route::post('settings/notification', [SettingController::class, 'updateNotification']);
    Route::get('settings/seo', [SettingController::class, 'seo'])->name('settings.seo');
    Route::post('settings/seo', [SettingController::class, 'updateSeo']);

    // Страницы
    Route::resource('pages', PageController::class);
    Route::post('pages/{page}/toggle-status', [PageController::class, 'toggleStatus'])->name('pages.toggle-status');
    Route::post('pages/reorder', [PageController::class, 'reorder'])->name('pages.reorder');

    // FAQ
    Route::resource('faqs', FAQController::class);
    Route::post('faqs/{faq}/toggle-status', [FAQController::class, 'toggleStatus'])->name('faqs.toggle-status');
    Route::post('faqs/reorder', [FAQController::class, 'reorder'])->name('faqs.reorder');

    // Предложения для FAQ
    Route::resource('faq-suggestions', FAQSuggestionController::class)->except(['create', 'store', 'edit', 'update']);
    Route::post('faq-suggestions/{suggestion}/approve', [FAQSuggestionController::class, 'approve'])->name('faq-suggestions.approve');
    Route::post('faq-suggestions/{suggestion}/reject', [FAQSuggestionController::class, 'reject'])->name('faq-suggestions.reject');

    // Отчеты
    Route::resource('reports', ReportController::class)->except(['edit', 'update']);
    Route::get('reports/{report}/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

    // Резервные копии
    Route::resource('backups', BackupController::class)->except(['edit', 'update']);
    Route::post('backups/create', [BackupController::class, 'createBackup'])->name('backups.create');
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::post('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('backups/cleanup', [BackupController::class, 'cleanup'])->name('backups.cleanup');

    // Логи аудита
    Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
    Route::post('audit-logs/cleanup', [AuditLogController::class, 'cleanup'])->name('audit-logs.cleanup');

    // Бан-лист
    Route::resource('ban-list', BanListController::class)->except(['show']);
    Route::post('ban-list/{ban}/unban', [BanListController::class, 'unban'])->name('ban-list.unban');
    Route::get('ban-list/ip', [BanListController::class, 'ipList'])->name('ban-list.ip');
    Route::post('ban-list/ip/{ip}/unban', [BanListController::class, 'unbanIp'])->name('ban-list.unban-ip');
});
