<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\PushVapidController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\RoomController as AdminRoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Публичные API маршруты
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Публичные данные
Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);
Route::get('/rooms/{room}/availability', [RoomController::class, 'checkAvailability']);

// Push подписки (публичные)
Route::get('/push/vapid-key', [PushVapidController::class, 'getKey']);
Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe']);
Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);

// Защищенные API маршруты (требуется аутентификация)
Route::middleware(['auth:sanctum'])->group(function () {

    // Аутентификация
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/email/verify/resend', [AuthController::class, 'resendVerificationEmail']);

    // Профиль
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::put('/notification-settings', [ProfileController::class, 'updateNotificationSettings']);
    });

    // Бронирования
    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        Route::get('/{booking}', [BookingController::class, 'show']);
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::get('/{booking}/invoice', [BookingController::class, 'invoice']);
    });

    // Номера
    Route::prefix('rooms')->group(function () {
        Route::post('/{room}/book', [RoomController::class, 'book']);
        Route::post('/{room}/favorite', [RoomController::class, 'toggleFavorite']);
        Route::get('/favorites', [RoomController::class, 'favorites']);
    });

    // Чат
    Route::prefix('chat')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'start']);
        Route::get('/{session}', [ChatController::class, 'show']);
        Route::post('/{session}/message', [ChatController::class, 'sendMessage']);
        Route::put('/{session}/close', [ChatController::class, 'close']);
    });

    // Уведомления
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    // Push уведомления
    Route::post('/push/send-test', [PushSubscriptionController::class, 'sendTestNotification']);
});

// Административные API маршруты
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // Пользователи
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::post('/', [AdminUserController::class, 'store']);
        Route::get('/{user}', [AdminUserController::class, 'show']);
        Route::put('/{user}', [AdminUserController::class, 'update']);
        Route::delete('/{user}', [AdminUserController::class, 'destroy']);
        Route::post('/{user}/ban', [AdminUserController::class, 'ban']);
        Route::post('/{user}/unban', [AdminUserController::class, 'unban']);
    });

    // Бронирования
    Route::prefix('bookings')->group(function () {
        Route::get('/', [AdminBookingController::class, 'index']);
        Route::get('/{booking}', [AdminBookingController::class, 'show']);
        Route::put('/{booking}', [AdminBookingController::class, 'update']);
        Route::post('/{booking}/confirm', [AdminBookingController::class, 'confirm']);
        Route::post('/{booking}/cancel', [AdminBookingController::class, 'cancel']);
        Route::post('/{booking}/check-in', [AdminBookingController::class, 'checkIn']);
        Route::post('/{booking}/check-out', [AdminBookingController::class, 'checkOut']);
    });

    // Номера
    Route::prefix('rooms')->group(function () {
        Route::get('/', [AdminRoomController::class, 'index']);
        Route::post('/', [AdminRoomController::class, 'store']);
        Route::get('/{room}', [AdminRoomController::class, 'show']);
        Route::put('/{room}', [AdminRoomController::class, 'update']);
        Route::delete('/{room}', [AdminRoomController::class, 'destroy']);
        Route::post('/{room}/toggle-status', [AdminRoomController::class, 'toggleStatus']);
        Route::post('/{room}/block-dates', [AdminRoomController::class, 'blockDates']);
    });
});
