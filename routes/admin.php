<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\StatisticsController;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::resource('users', UserController::class);
    Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');

    // Hotels
    Route::resource('hotels', HotelController::class);
    Route::post('hotels/{hotel}/toggle-status', [HotelController::class, 'toggleStatus'])->name('hotels.toggle-status');

    // Rooms
    Route::resource('rooms', RoomController::class);
    Route::get('rooms/{room}/availability', [RoomController::class, 'availability'])->name('rooms.availability');

    // Bookings
    Route::resource('bookings', BookingController::class);
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // Payments
    Route::resource('payments', PaymentController::class);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

    // Discounts
    Route::resource('discounts', DiscountController::class);

    // Reviews
    Route::resource('reviews', ReviewController::class);
    Route::post('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');

    // Notifications
    Route::resource('notifications', NotificationController::class);
    Route::post('notifications/send-bulk', [NotificationController::class, 'sendBulk'])->name('notifications.send-bulk');

    // Chat
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{user}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/{user}/message', [ChatController::class, 'sendMessage'])->name('chat.send');

    // Statistics
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('statistics/bookings', [StatisticsController::class, 'bookings'])->name('statistics.bookings');
    Route::get('statistics/revenue', [StatisticsController::class, 'revenue'])->name('statistics.revenue');
    Route::get('statistics/users', [StatisticsController::class, 'users'])->name('statistics.users');
});
