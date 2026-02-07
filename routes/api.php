<?php

use Illuminate\Support\Facades\Route;
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

// Номера (публичные)
Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);
Route::post('/rooms/availability', [RoomController::class, 'checkAvailability']);

// VAPID ключи для push-уведомлений
Route::get('/push/vapid-public-key', [PushVapidController::class, 'getPublicKey']);

// Защищенные API маршруты (требуют аутентификации)
Route::middleware(['auth:sanctum'])->group(function () {
    // Профиль
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Бронирования
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::get('/bookings/{booking}/invoice', [BookingController::class, 'invoice']);

    // Чат
    Route::get('/chat/sessions', [ChatController::class, 'sessions']);
    Route::post('/chat/sessions', [ChatController::class, 'createSession']);
    Route::get('/chat/sessions/{session}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/sessions/{session}/messages', [ChatController::class, 'sendMessage']);
    Route::put('/chat/messages/{message}/read', [ChatController::class, 'markAsRead']);
    Route::post('/chat/sessions/{session}/resolve', [ChatController::class, 'resolveSession']);

    // Уведомления
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/clear', [NotificationController::class, 'clear']);

    // Push-подписки
    Route::prefix('push-subscriptions')->group(function () {
        Route::get('/', [PushSubscriptionController::class, 'index']);
        Route::post('/', [PushSubscriptionController::class, 'store']);
        Route::delete('/', [PushSubscriptionController::class, 'destroy']);
        Route::post('/test', [PushSubscriptionController::class, 'sendTest']);
    });

    // Выход
    Route::post('/logout', [AuthController::class, 'logout']);
});

// API маршруты для администраторов
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Пользователи
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    Route::post('/users/{user}/ban', [AdminUserController::class, 'ban']);
    Route::post('/users/{user}/unban', [AdminUserController::class, 'unban']);

    // Бронирования
    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::get('/bookings/{booking}', [AdminBookingController::class, 'show']);
    Route::put('/bookings/{booking}', [AdminBookingController::class, 'update']);
    Route::post('/bookings/{booking}/confirm', [AdminBookingController::class, 'confirm']);
    Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/complete', [AdminBookingController::class, 'complete']);

    // Номера
    Route::get('/rooms', [AdminRoomController::class, 'index']);
    Route::post('/rooms', [AdminRoomController::class, 'store']);
    Route::get('/rooms/{room}', [AdminRoomController::class, 'show']);
    Route::put('/rooms/{room}', [AdminRoomController::class, 'update']);
    Route::delete('/rooms/{room}', [AdminRoomController::class, 'destroy']);
    Route::post('/rooms/{room}/toggle-status', [AdminRoomController::class, 'toggleStatus']);
});
