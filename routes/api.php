<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// API версия
Route::prefix('v1')->group(function () {

    // Публичные API
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);

    // Номера (публичные)
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{room}', [RoomController::class, 'show']);
    Route::post('/rooms/availability', [RoomController::class, 'checkAvailability']);

    // Аутентифицированные API
    Route::middleware(['auth:sanctum'])->group(function () {
        // Профиль
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);

        // Бронирования
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        // Чат
        Route::get('/chat', [\App\Http\Controllers\Api\ChatController::class, 'index']);
        Route::post('/chat', [\App\Http\Controllers\Api\ChatController::class, 'send']);
        Route::post('/chat/{message}/read', [\App\Http\Controllers\Api\ChatController::class, 'markAsRead']);

        // Уведомления
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);

        // Выход
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Админ API (дополнительная защита)
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin.api'])->group(function () {
        Route::apiResource('users', \App\Http\Controllers\Api\Admin\UserController::class);
        Route::apiResource('bookings', \App\Http\Controllers\Api\Admin\BookingController::class);
        Route::apiResource('rooms', \App\Http\Controllers\Api\Admin\RoomController::class);

        Route::post('users/{user}/ban', [\App\Http\Controllers\Api\Admin\UserController::class, 'ban']);
        Route::post('bookings/{booking}/confirm', [\App\Http\Controllers\Api\Admin\BookingController::class, 'confirm']);
    });
});
