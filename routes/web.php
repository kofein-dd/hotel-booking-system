<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\NotificationController as UserNotificationController;
use App\Http\Controllers\ChatController as UserChatController;
use App\Http\Controllers\Frontend\HotelController as FrontendHotelController;
use App\Http\Controllers\Frontend\RoomController as FrontendRoomController;
use App\Http\Controllers\Frontend\BookingController as FrontendBookingController;
use App\Http\Controllers\Frontend\PaymentController as FrontendPaymentController;
use App\Http\Controllers\Frontend\ReviewController as FrontendReviewController;
use App\Http\Controllers\Frontend\PageController as FrontendPageController;
use App\Http\Controllers\Frontend\FAQController as FrontendFAQController;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

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

// Аутентификация
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Выход
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ========== ПУБЛИЧНЫЕ МАРШРУТЫ ==========

// Отель
Route::prefix('hotel')->name('hotel.')->group(function () {
    Route::get('/about', [FrontendHotelController::class, 'about'])->name('about');
    Route::get('/contacts', [FrontendHotelController::class, 'contacts'])->name('contacts');
    Route::get('/gallery', [FrontendHotelController::class, 'gallery'])->name('gallery');
    Route::get('/', [FrontendHotelController::class, 'index'])->name('index');
});

// Номера
Route::get('/rooms', [FrontendRoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room}', [FrontendRoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room}/availability', [FrontendRoomController::class, 'checkAvailability'])->name('rooms.availability');

// Страницы
Route::get('/pages/{slug}', [FrontendPageController::class, 'show'])->name('pages.show');

// FAQ
Route::get('/faq', [FrontendFAQController::class, 'index'])->name('faq.index');

// Контакты
Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');

// Поиск
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/rooms', [SearchController::class, 'searchRooms'])->name('search.rooms');

// Карта сайта
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// ========== МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ ==========
Route::middleware(['auth', 'verified'])->group(function () {

    // Профиль
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/update', [ProfileController::class, 'update'])->name('update');
        Route::put('/update-password', [ProfileController::class, 'updatePassword'])->name('update.password');
        Route::delete('/delete', [ProfileController::class, 'destroy'])->name('destroy');

        // Бронирования пользователя
        Route::get('/bookings', [ProfileController::class, 'bookings'])->name('bookings');
        Route::get('/bookings/{booking}', [FrontendBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [FrontendBookingController::class, 'cancel'])->name('bookings.cancel');

        // Отзывы
        Route::get('/reviews', [FrontendReviewController::class, 'index'])->name('reviews');
        Route::get('/reviews/create/{booking?}', [FrontendReviewController::class, 'create'])->name('reviews.create');
        Route::post('/reviews', [FrontendReviewController::class, 'store'])->name('reviews.store');
        Route::get('/reviews/{review}/edit', [FrontendReviewController::class, 'edit'])->name('reviews.edit');
        Route::put('/reviews/{review}', [FrontendReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{review}', [FrontendReviewController::class, 'destroy'])->name('reviews.destroy');

        // Уведомления
        Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/mark-all-read', [UserNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::put('/notifications/{notification}', [UserNotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::delete('/notifications/{notification}', [UserNotificationController::class, 'destroy'])->name('notifications.destroy');

        // Настройки уведомлений
        Route::get('/notification-settings', [UserNotificationController::class, 'settings'])->name('notification-settings');
        Route::put('/notification-settings', [UserNotificationController::class, 'updateSettings'])->name('notification-settings.update');
    });

    // Процесс бронирования
    Route::prefix('booking')->name('booking.')->group(function () {
        Route::get('/step1', [FrontendBookingController::class, 'step1'])->name('step1');
        Route::post('/step1', [FrontendBookingController::class, 'processStep1']);
        Route::get('/step2', [FrontendBookingController::class, 'step2'])->name('step2');
        Route::post('/step2', [FrontendBookingController::class, 'processStep2']);
        Route::get('/step3', [FrontendBookingController::class, 'step3'])->name('step3');
        Route::post('/step3', [FrontendBookingController::class, 'processStep3']);
        Route::get('/step4', [FrontendBookingController::class, 'step4'])->name('step4');
        Route::post('/confirm', [FrontendBookingController::class, 'confirm'])->name('confirm');
        Route::get('/success/{booking}', [FrontendBookingController::class, 'success'])->name('success');
    });

    // Оплата
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::get('/{booking}', [FrontendPaymentController::class, 'index'])->name('index');
        Route::post('/process/{booking}', [FrontendPaymentController::class, 'process'])->name('process');
        Route::get('/success/{payment}', [FrontendPaymentController::class, 'success'])->name('success');
        Route::get('/cancel/{payment}', [FrontendPaymentController::class, 'cancel'])->name('cancel');
    });

    // Чат с поддержкой
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [UserChatController::class, 'index'])->name('index');
        Route::get('/session/{session}', [UserChatController::class, 'show'])->name('show');
        Route::post('/session/{session}/message', [UserChatController::class, 'sendMessage'])->name('send-message');
        Route::post('/start', [UserChatController::class, 'startChat'])->name('start');
        Route::put('/session/{session}/close', [UserChatController::class, 'closeSession'])->name('close');
    });
});

// Верификация email
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/profile');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Ссылка для подтверждения отправлена!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
