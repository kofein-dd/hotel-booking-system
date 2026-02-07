<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Frontend\RoomController as FrontendRoomController;
use App\Http\Controllers\Frontend\BookingController as FrontendBookingController;
use App\Http\Controllers\Frontend\PaymentController as FrontendPaymentController;
use App\Http\Controllers\Frontend\ReviewController as FrontendReviewController;
use Illuminate\Support\Facades\Route;

// Главная страница
Route::get('/', [HomeController::class, 'index'])->name('home');

// Страницы отеля
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');

// Поиск номеров
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/search/availability', [SearchController::class, 'checkAvailability'])->name('search.availability');

// Номера (публичный просмотр)
Route::get('/rooms', [FrontendRoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room}', [FrontendRoomController::class, 'show'])->name('rooms.show');

// Отзывы (публичные)
Route::get('/reviews', [FrontendReviewController::class, 'index'])->name('reviews.index');
Route::get('/hotel/reviews', [FrontendReviewController::class, 'hotelReviews'])->name('reviews.hotel');

// Аутентифицированные маршруты
Route::middleware(['auth'])->group(function () {
    // Личный кабинет
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Бронирования пользователя
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('/bookings', [FrontendBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [FrontendBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [FrontendBookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/extend', [FrontendBookingController::class, 'extend'])->name('bookings.extend');
    });

    // Бронирование (процесс)
    Route::post('/rooms/{room}/book', [FrontendBookingController::class, 'store'])->name('bookings.store');
    Route::get('/booking/{booking}/confirm', [FrontendBookingController::class, 'confirm'])->name('bookings.confirm');

    // Оплата
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::get('/{booking}', [FrontendPaymentController::class, 'create'])->name('create');
        Route::post('/{booking}/process', [FrontendPaymentController::class, 'process'])->name('process');
        Route::get('/{booking}/success', [FrontendPaymentController::class, 'success'])->name('success');
        Route::get('/{booking}/cancel', [FrontendPaymentController::class, 'cancel'])->name('cancel');
    });

    // Отзывы пользователя
    Route::post('/reviews', [FrontendReviewController::class, 'store'])->name('reviews.store');
    Route::put('/reviews/{review}', [FrontendReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [FrontendReviewController::class, 'destroy'])->name('reviews.destroy');

    // Чат с поддержкой (будет в API)

    // Маршруты для бронирований пользователя
    Route::prefix('my-bookings')->name('bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
    });

    // Маршруты для чата
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::get('/{session}', [ChatController::class, 'show'])->name('show');
        Route::post('/{session}/message', [ChatController::class, 'sendMessage'])->name('send');
        Route::post('/start', [ChatController::class, 'start'])->name('start');
        Route::post('/{session}/resolve', [ChatController::class, 'resolve'])->name('resolve');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::delete('/notifications/clear', [NotificationController::class, 'clear'])->name('notifications.clear');

    // Push-подписки
    Route::prefix('push-subscriptions')->group(function () {
        Route::get('/', [PushSubscriptionController::class, 'index']);
        Route::post('/', [PushSubscriptionController::class, 'store']);
        Route::delete('/', [PushSubscriptionController::class, 'destroy']);
        Route::post('/test', [PushSubscriptionController::class, 'sendTest']);
    });
});

// Статические страницы (из админки)
Route::get('/page/{slug}', [App\Http\Controllers\Frontend\PageController::class, 'show'])->name('page.show');
Route::get('/faq', [App\Http\Controllers\Frontend\FAQController::class, 'index'])->name('faq.index');

// Sitemap
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// Подключение маршрутов аутентификации
require __DIR__.'/auth.php';

// Публичные маршруты
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');

Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');

Route::middleware(['auth'])->group(function () {
    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
});

// FAQ маршруты
Route::get('/faq', [FAQController::class, 'index'])->name('faq.index');
Route::get('/faq/suggestions/{id}/status', [FAQController::class, 'suggestionStatus'])->name('faq.suggestion-status');
