<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ChatController as UserChatController;

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

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/hotel', [HomeController::class, 'hotel'])->name('hotel');
Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/availability', [RoomController::class, 'checkAvailability'])->name('rooms.availability');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');

// Authentication routes (будет добавлено Breeze)
Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    // Chat
    Route::get('/chat', [UserChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/message', [UserChatController::class, 'sendMessage'])->name('chat.send');
});

// Include admin routes
require __DIR__.'/admin.php';

// Include auth routes (будет сгенерировано Breeze)
require __DIR__.'/auth.php';
