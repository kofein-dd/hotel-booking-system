<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PushSubscriptionController extends Controller
{
    /**
     * Сохранить push-подписку
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|url',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Проверяем, существует ли уже такая подписка
        $existing = PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            // Обновляем существующую подписку
            $existing->update([
                'auth_token' => $request->keys['auth'],
                'public_key' => $request->keys['p256dh'],
                'last_used_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Подписка обновлена',
                'subscription' => $existing
            ]);
        }

        // Создаем новую подписку
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $request->endpoint,
            'auth_token' => $request->keys['auth'],
            'public_key' => $request->keys['p256dh'],
            'content_encoding' => 'aesgcm',
            'device_info' => $request->header('User-Agent'),
            'browser_info' => $request->header('Sec-CH-UA'),
            'last_used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Подписка сохранена',
            'subscription' => $subscription
        ], 201);
    }

    /**
     * Удалить push-подписку
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $deleted = PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Подписка удалена'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Подписка не найдена'
        ], 404);
    }

    /**
     * Получить все подписки пользователя
     */
    public function index()
    {
        $user = Auth::user();

        $subscriptions = $user->pushSubscriptions()
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'subscriptions' => $subscriptions
        ]);
    }

    /**
     * Отправить тестовое push-уведомление
     */
    public function sendTest(Request $request)
    {
        $user = Auth::user();

        // Проверяем, есть ли активные подписки
        if (!$user->pushSubscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активных push-подписок'
            ], 400);
        }

        // Отправляем тестовое уведомление
        $user->notify(new \App\Notifications\TestPushNotification('test', [
            'test_id' => uniqid(),
            'timestamp' => now()->toDateTimeString(),
            'message' => 'Тестовое push-уведомление успешно отправлено!',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Тестовое уведомление отправлено'
        ]);
    }
}
