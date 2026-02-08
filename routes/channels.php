<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\ChatSession;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Канал для уведомлений пользователя
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Канал для чат-сессий
Broadcast::channel('chat.{sessionId}', function ($user, $sessionId) {
    $session = ChatSession::find($sessionId);

    if (!$session) {
        return false;
    }

    // Пользователь может слушать канал, если он участник чата
    if ($session->user_id === $user->id) {
        return true;
    }

    // Администратор может слушать любой чат
    if ($user->hasRole('admin')) {
        return true;
    }

    return false;
});

// Канал для административных уведомлений
Broadcast::channel('admin.notifications', function ($user) {
    return $user->hasRole('admin');
});

// Канал для статистики в реальном времени
Broadcast::channel('admin.statistics', function ($user) {
    return $user->hasRole('admin');
});
