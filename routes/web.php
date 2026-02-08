<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Frontend\RoomController as FrontendRoomController;
use App\Http\Controllers\Frontend\BookingController as FrontendBookingController;
use App\Http\Controllers\Frontend\PaymentController as FrontendPaymentController;
use App\Http\Controllers\Frontend\ReviewController as FrontendReviewController;
use App\Http\Controllers\Frontend\PageController as FrontendPageController;
use App\Http\Controllers\Frontend\FAQController as FrontendFAQController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Главная страница
Route::get('/', [HomeController::class, 'index'])->name('home');

// Публичные маршруты
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/services', [HomeController::class, 'services'])->name('services');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');

// Страницы
Route::get('/pages/{slug}', [FrontendPageController::class, 'show'])->name('pages.show');

// FAQ
Route::get('/faq', [FrontendFAQController::class, 'index'])->name('faq.index');
Route::get('/faq/category/{category}', [FrontendFAQController::class, 'byCategory'])->name('faq.category');
Route::get('/faq/suggestions/{id}/status', [FrontendFAQController::class, 'suggestionStatus'])->name('faq.suggestion-status');

// Номера
Route::get('/rooms', [FrontendRoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room}', [FrontendRoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room}/availability', [FrontendRoomController::class, 'checkAvailability'])->name('rooms.availability');

// Поиск
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/search/availability', [SearchController::class, 'checkAvailability'])->name('search.availability');

// Аутентификация
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

// FAQ маршруты - ДОБАВЛЯЕМ ЭТОТ БЛОК
Route::get('/faq', [FAQController::class, 'index'])->name('faq.index');

// Публичные маршруты для отелей
Route::prefix('hotels')->name('hotels.')->group(function () {
    Route::get('/', [HomeController::class, 'hotels'])->name('index');
    Route::get('/{slug}', [HomeController::class, 'hotelShow'])->name('show');
});

// Публичные маршруты для номеров
Route::prefix('rooms')->name('rooms.')->group(function () {
    Route::get('/', [HomeController::class, 'rooms'])->name('index');
    Route::get('/{slug}', [HomeController::class, 'roomShow'])->name('show');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout');

// Подтверждение email
Route::get('/email/verify', [AuthController::class, 'showVerificationNotice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['auth', 'signed'])->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Сброс пароля
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

// Защищенные маршруты (требуют аутентификации)
Route::middleware(['auth', 'verified'])->group(function () {
    // Профиль
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Бронирования пользователя
    Route::prefix('my-bookings')->name('bookings.')->group(function () {
        Route::get('/', [FrontendBookingController::class, 'index'])->name('index');
        Route::get('/{booking}', [FrontendBookingController::class, 'show'])->name('show');
        Route::post('/{booking}/cancel', [FrontendBookingController::class, 'cancel'])->name('cancel');
        Route::get('/{booking}/invoice', [FrontendBookingController::class, 'invoice'])->name('invoice');
    });

    // Бронирование номеров
    Route::prefix('booking')->name('booking.')->group(function () {
        Route::get('/create/{room}', [FrontendBookingController::class, 'create'])->name('create');
        Route::post('/store', [FrontendBookingController::class, 'store'])->name('store');
        Route::get('/confirm/{booking}', [FrontendBookingController::class, 'confirm'])->name('confirm');
    });

    // Платежи
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::get('/{booking}', [FrontendPaymentController::class, 'create'])->name('create');
        Route::post('/{booking}/process', [FrontendPaymentController::class, 'process'])->name('process');
        Route::get('/{booking}/success', [FrontendPaymentController::class, 'success'])->name('success');
        Route::get('/{booking}/cancel', [FrontendPaymentController::class, 'cancel'])->name('cancel');
    });

    // Отзывы
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', [FrontendReviewController::class, 'index'])->name('index');
        Route::get('/create/{booking}', [FrontendReviewController::class, 'create'])->name('create');
        Route::post('/store', [FrontendReviewController::class, 'store'])->name('store');
        Route::get('/{review}', [FrontendReviewController::class, 'show'])->name('show');
        Route::put('/{review}', [FrontendReviewController::class, 'update'])->name('update');
        Route::delete('/{review}', [FrontendReviewController::class, 'destroy'])->name('destroy');
    });

    // Чат
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ChatController::class, 'index'])->name('index');
        Route::get('/{session}', [\App\Http\Controllers\ChatController::class, 'show'])->name('show');
        Route::post('/{session}/message', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('send');
        Route::post('/start', [\App\Http\Controllers\ChatController::class, 'start'])->name('start');
        Route::post('/{session}/resolve', [\App\Http\Controllers\ChatController::class, 'resolve'])->name('resolve');
    });

    // Уведомления
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-as-read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::delete('/notifications/clear', [\App\Http\Controllers\NotificationController::class, 'clear'])->name('notifications.clear');

    // Предложения для FAQ
    Route::post('/faq/suggest', [FrontendFAQController::class, 'suggestQuestion'])->name('faq.suggest');
});

// Маршруты только для администраторов
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Пользователи
    Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
    Route::post('users/{user}/ban', [\App\Http\Controllers\Admin\UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [\App\Http\Controllers\Admin\UserController::class, 'unban'])->name('users.unban');

    // Отели
    Route::resource('hotels', \App\Http\Controllers\Admin\HotelController::class);

    // Номера
    Route::resource('rooms', \App\Http\Controllers\Admin\RoomController::class);
    Route::post('rooms/{room}/toggle-status', [\App\Http\Controllers\Admin\RoomController::class, 'toggleStatus'])->name('rooms.toggle-status');

    // Бронирования
    Route::resource('bookings', \App\Http\Controllers\Admin\BookingController::class);
    Route::post('bookings/{booking}/confirm', [\App\Http\Controllers\Admin\BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{booking}/cancel', [\App\Http\Controllers\Admin\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/complete', [\App\Http\Controllers\Admin\BookingController::class, 'complete'])->name('bookings.complete');

    // Платежи
    Route::resource('payments', \App\Http\Controllers\Admin\PaymentController::class);

    // Отзывы
    Route::resource('reviews', \App\Http\Controllers\Admin\ReviewController::class);
    Route::post('reviews/{review}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('reviews/{review}/reject', [\App\Http\Controllers\Admin\ReviewController::class, 'reject'])->name('reviews.reject');

    // Жалобы на отзывы
    Route::resource('review-reports', \App\Http\Controllers\Admin\ReviewReportController::class);

    // Уведомления
    Route::resource('notifications', \App\Http\Controllers\Admin\NotificationController::class);
    Route::post('notifications/send-to-all', [\App\Http\Controllers\Admin\NotificationController::class, 'sendToAll'])->name('notifications.send-to-all');

    // Чат
    Route::get('chat', [\App\Http\Controllers\Admin\ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{session}', [\App\Http\Controllers\Admin\ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/{session}/message', [\App\Http\Controllers\Admin\ChatController::class, 'sendMessage'])->name('chat.send');

    // Скидки
    Route::resource('discounts', \App\Http\Controllers\Admin\DiscountController::class);
    Route::post('discounts/{discount}/toggle-status', [\App\Http\Controllers\Admin\DiscountController::class, 'toggleStatus'])->name('discounts.toggle-status');

    // Статистика
    Route::get('statistics', [\App\Http\Controllers\Admin\StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('statistics/bookings', [\App\Http\Controllers\Admin\StatisticsController::class, 'bookings'])->name('statistics.bookings');
    Route::get('statistics/revenue', [\App\Http\Controllers\Admin\StatisticsController::class, 'revenue'])->name('statistics.revenue');
    Route::get('statistics/users', [\App\Http\Controllers\Admin\StatisticsController::class, 'users'])->name('statistics.users');

    // Настройки
    Route::resource('settings', \App\Http\Controllers\Admin\SettingController::class);

    // Страницы
    Route::resource('pages', \App\Http\Controllers\Admin\PageController::class);

    // FAQ
    Route::resource('faq', \App\Http\Controllers\Admin\FAQController::class);
    Route::post('faq/{faq}/toggle-status', [\App\Http\Controllers\Admin\FAQController::class, 'toggleStatus'])->name('faq.toggle-status');

    // Предложения для FAQ
    Route::resource('faq-suggestions', \App\Http\Controllers\Admin\FAQSuggestionController::class);
    Route::post('faq-suggestions/{suggestion}/approve', [\App\Http\Controllers\Admin\FAQSuggestionController::class, 'approve'])->name('faq-suggestions.approve');
    Route::post('faq-suggestions/{suggestion}/reject', [\App\Http\Controllers\Admin\FAQSuggestionController::class, 'reject'])->name('faq-suggestions.reject');
    Route::post('faq-suggestions/{suggestion}/add-to-faq', [\App\Http\Controllers\Admin\FAQSuggestionController::class, 'addToFaq'])->name('faq-suggestions.add-to-faq');

    // Отчеты
    Route::resource('reports', \App\Http\Controllers\Admin\ReportController::class);

    // Аудит-логи
    Route::resource('audit-logs', \App\Http\Controllers\Admin\AuditLogController::class);
    Route::get('audit-logs/export/excel', [\App\Http\Controllers\Admin\AuditLogController::class, 'exportExcel'])->name('audit-logs.export.excel');

    // Бэкапы
    Route::resource('backups', \App\Http\Controllers\Admin\BackupController::class);
    Route::post('backups/create', [\App\Http\Controllers\Admin\BackupController::class, 'createBackup'])->name('backups.create');
    Route::post('backups/{backup}/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backups.restore');

    // Бан-лист
    Route::resource('ban-list', \App\Http\Controllers\Admin\BanListController::class);
});

// Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');

// Webhooks
Route::post('/webhook/payment/{gateway}', [\App\Http\Controllers\WebhookController::class, 'handlePayment'])->name('webhook.payment');
Route::post('/webhook/telegram', [\App\Http\Controllers\WebhookController::class, 'handleTelegram'])->name('webhook.telegram');

// Cron задачи
Route::get('/cron/send-reminders', [\App\Http\Controllers\CronController::class, 'sendBookingReminders'])->name('cron.reminders');
Route::get('/cron/cleanup-expired', [\App\Http\Controllers\CronController::class, 'cleanupExpiredBookings'])->name('cron.cleanup');

// Отладка маршрутов
Route::get('/routes', function() {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $routeList = [];

    foreach ($routes as $route) {
        $routeList[] = [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    }

    return view('routes.debug', compact('routeList'));
})->middleware('admin')->name('routes.debug');
