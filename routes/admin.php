<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
    DashboardController,
    UserController,
    HotelController,
    RoomController,
    BookingController,
    PaymentController,
    ReviewController,
    NotificationController,
    ChatController,
    DiscountController,
    StatisticsController,
    SettingController,
    ReportController,
    BackupController,
    PageController,
    FAQController
};

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    // Дашборд
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Пользователи
    Route::resource('users', UserController::class);
    Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
    Route::get('users/{user}/activity', [UserController::class, 'activity'])->name('users.activity');

    // Отель
    Route::resource('hotels', HotelController::class)->except(['index', 'create', 'destroy']);
    Route::post('hotels/media', [HotelController::class, 'storeMedia'])->name('hotels.storeMedia');
    Route::delete('hotels/media/{media}', [HotelController::class, 'deleteMedia'])->name('hotels.deleteMedia');

    // Номера
    Route::resource('rooms', RoomController::class);
    Route::post('rooms/{room}/toggle', [RoomController::class, 'toggle'])->name('rooms.toggle');
    Route::post('rooms/media', [RoomController::class, 'storeMedia'])->name('rooms.storeMedia');
    Route::delete('rooms/media/{media}', [RoomController::class, 'deleteMedia'])->name('rooms.deleteMedia');

    // Бронирования
    Route::resource('bookings', BookingController::class);
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/reject', [BookingController::class, 'reject'])->name('bookings.reject');
    Route::get('bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');

    // Платежи
    Route::resource('payments', PaymentController::class);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

    // Отзывы
    Route::resource('reviews', ReviewController::class);
    Route::get('reviews/reported', [ReviewController::class, 'reported'])->name('reviews.reported');
    Route::post('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');

    // Уведомления
    Route::resource('notifications', NotificationController::class);
    Route::post('notifications/send', [NotificationController::class, 'sendNotification'])->name('notifications.send');
    Route::post('notifications/broadcast', [NotificationController::class, 'broadcast'])->name('notifications.broadcast');

    // Чат
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('chats/{user}', [ChatController::class, 'show'])->name('chats.show');
    Route::post('chats/{user}', [ChatController::class, 'send'])->name('chats.send');
    Route::post('chats/{message}/read', [ChatController::class, 'markAsRead'])->name('chats.read');

    // Скидки и промокоды
    Route::resource('discounts', DiscountController::class);
    Route::post('discounts/{discount}/activate', [DiscountController::class, 'activate'])->name('discounts.activate');
    Route::post('discounts/{discount}/deactivate', [DiscountController::class, 'deactivate'])->name('discounts.deactivate');

    // Статистика
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('statistics/bookings', [StatisticsController::class, 'bookings'])->name('statistics.bookings');
    Route::get('statistics/revenue', [StatisticsController::class, 'revenue'])->name('statistics.revenue');
    Route::get('statistics/users', [StatisticsController::class, 'users'])->name('statistics.users');
    Route::get('statistics/export', [StatisticsController::class, 'export'])->name('statistics.export');

    // Настройки
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/clear-cache', [SettingController::class, 'clearCache'])->name('settings.clearCache');

    // Отчеты
    Route::resource('reports', ReportController::class);
    Route::get('reports/generate/{type}', [ReportController::class, 'generate'])->name('reports.generate');
    Route::post('reports/export/{report}', [ReportController::class, 'export'])->name('reports.export');

    // Бекапы
    Route::resource('backups', BackupController::class);
    Route::post('backups/create', [BackupController::class, 'createBackup'])->name('backups.create');
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::post('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');

    // Страницы
    Route::resource('pages', PageController::class);

    // FAQ
    Route::resource('faqs', FAQController::class);

    // Аудит логи
    Route::get('audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Admin\AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::delete('audit-logs/clear', [\App\Http\Controllers\Admin\AuditLogController::class, 'clear'])->name('audit-logs.clear');

    // Бан-лист
    Route::get('ban-list', [\App\Http\Controllers\Admin\BanListController::class, 'index'])->name('ban-list.index');
    Route::delete('ban-list/{banList}', [\App\Http\Controllers\Admin\BanListController::class, 'destroy'])->name('ban-list.destroy');

    // Журнал отчетов на отзывы
    Route::get('review-reports', [\App\Http\Controllers\Admin\ReviewReportController::class, 'index'])->name('review-reports.index');
    Route::delete('review-reports/{reviewReport}', [\App\Http\Controllers\Admin\ReviewReportController::class, 'destroy'])->name('review-reports.destroy');
    Route::post('review-reports/{reviewReport}/resolve', [\App\Http\Controllers\Admin\ReviewReportController::class, 'resolve'])->name('review-reports.resolve');

    // FAQ в админке
    Route::resource('faqs', \App\Http\Controllers\Admin\FAQController::class);

    // Предложения FAQ
    Route::resource('faq-suggestions', \App\Http\Controllers\Admin\FAQSuggestionController::class);
});

Route::prefix('faq')->name('faq.')->group(function () {
    Route::resource('suggestions', FAQSuggestionController::class);
    Route::post('suggestions/{suggestion}/approve', [FAQSuggestionController::class, 'approve'])->name('suggestions.approve');
    Route::post('suggestions/{suggestion}/reject', [FAQSuggestionController::class, 'reject'])->name('suggestions.reject');
    Route::post('suggestions/{suggestion}/add-to-faq', [FAQSuggestionController::class, 'addToFaq'])->name('suggestions.add-to-faq');
});
