<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Display a listing of user notifications.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $query = Notification::where('user_id', $user->id)
                ->orWhereNull('user_id') // Системные уведомления
                ->orderBy('created_at', 'desc');

            // Фильтр по типу уведомления
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Фильтр по статусу прочтения
            if ($request->has('read') && $request->read !== null) {
                $query->where('read_at', $request->read ? '!=' : '=', null);
            }

            // Фильтр по дате
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
            }

            // Поиск по тексту
            if ($request->has('search') && $request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('message', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Статистика
            $stats = [
                'total' => Notification::where('user_id', $user->id)
                    ->orWhereNull('user_id')
                    ->count(),
                'unread' => Notification::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })->whereNull('read_at')->count(),
                'today' => Notification::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })->whereDate('created_at', today())->count(),
            ];

            // Типы уведомлений пользователя
            $types = Notification::select('type', DB::raw('COUNT(*) as count'))
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get();

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'stats' => $stats,
                'types' => $types,
                'meta' => [
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request)
    {
        try {
            $user = $request->user();

            $count = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })->whereNull('read_at')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении количества непрочитанных уведомлений'
            ], 500);
        }
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();

            $notification = Notification::findOrFail($id);

            // Проверяем права доступа
            if ($notification->user_id && $notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Если уведомление уже прочитано
            if ($notification->read_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Уведомление уже отмечено как прочитанное',
                    'data' => new NotificationResource($notification)
                ]);
            }

            $notification->update([
                'read_at' => now(),
                'read_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Уведомление отмечено как прочитанное',
                'data' => new NotificationResource($notification)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении уведомления',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();

            $updated = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })->whereNull('read_at')->update([
                'read_at' => now(),
                'read_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "$updated уведомлений отмечено как прочитанные",
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(Request $request, $id)
    {
        try {
            $user = $request->user();

            $notification = Notification::findOrFail($id);

            // Проверяем права доступа
            if ($notification->user_id && $notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $notification->update([
                'read_at' => null,
                'read_by' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Уведомление отмечено как непрочитанное',
                'data' => new NotificationResource($notification)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении уведомления',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a notification.
     */
    public function delete(Request $request, $id)
    {
        try {
            $user = $request->user();

            $notification = Notification::findOrFail($id);

            // Проверяем права доступа
            if ($notification->user_id && $notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Уведомление удалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении уведомления',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete all read notifications.
     */
    public function deleteAllRead(Request $request)
    {
        try {
            $user = $request->user();

            $deleted = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })->whereNotNull('read_at')->delete();

            return response()->json([
                'success' => true,
                'message' => "$deleted прочитанных уведомлений удалено",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get notification preferences/settings.
     */
    public function getPreferences(Request $request)
    {
        try {
            $user = $request->user();

            $preferences = $user->notification_preferences ?? [
                'email' => [
                    'booking_confirmation' => true,
                    'booking_reminder' => true,
                    'payment_success' => true,
                    'payment_failed' => true,
                    'review_request' => true,
                    'special_offers' => true,
                    'newsletter' => false,
                ],
                'push' => [
                    'booking_confirmation' => true,
                    'booking_reminder' => true,
                    'chat_message' => true,
                    'admin_announcement' => true,
                ],
                'sms' => [
                    'booking_confirmation' => false,
                    'booking_reminder' => false,
                    'emergency' => true,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении настроек уведомлений'
            ], 500);
        }
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'preferences' => 'required|array',
                'preferences.email' => 'array',
                'preferences.push' => 'array',
                'preferences.sms' => 'array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $currentPreferences = $user->notification_preferences ?? [];
            $newPreferences = array_merge($currentPreferences, $request->preferences);

            $user->update([
                'notification_preferences' => $newPreferences
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Настройки уведомлений обновлены',
                'data' => $newPreferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении настроек уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get notification statistics.
     */
    public function statistics(Request $request)
    {
        try {
            $user = $request->user();

            $period = $request->get('period', '30days');

            switch ($period) {
                case '7days':
                    $startDate = Carbon::now()->subDays(7);
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(30);
                    break;
                case '90days':
                    $startDate = Carbon::now()->subDays(90);
                    break;
                default:
                    $startDate = Carbon::now()->subDays(30);
            }

            // Уведомления по дням
            $notificationsByDay = Notification::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->date => $item->count];
                });

            // Уведомления по типам
            $notificationsByType = Notification::select(
                'type',
                DB::raw('COUNT(*) as count')
            )
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })
                ->where('created_at', '>=', $startDate)
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get();

            // Статус прочтения
            $readStats = Notification::select(
                DB::raw('CASE WHEN read_at IS NULL THEN "unread" ELSE "read" END as status'),
                DB::raw('COUNT(*) as count')
            )
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })
                ->where('created_at', '>=', $startDate)
                ->groupBy('status')
                ->get();

            // Уведомления по времени суток
            $notificationsByHour = Notification::select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                })
                ->where('created_at', '>=', $startDate)
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications_by_day' => $notificationsByDay,
                    'notifications_by_type' => $notificationsByType,
                    'read_stats' => $readStats,
                    'notifications_by_hour' => $notificationsByHour,
                    'period' => $period,
                    'total_notifications_period' => Notification::where(function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->orWhereNull('user_id');
                    })->where('created_at', '>=', $startDate)->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики'
            ], 500);
        }
    }

    /**
     * Send test notification.
     */
    public function sendTest(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'type' => 'required|string|in:email,push,sms',
                'message' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = $request->type;
            $message = $request->message ?? 'Это тестовое уведомление для проверки настроек';

            switch ($type) {
                case 'email':
                    $this->sendTestEmail($user, $message);
                    break;
                case 'push':
                    $this->sendTestPush($user, $message);
                    break;
                case 'sms':
                    $this->sendTestSMS($user, $message);
                    break;
            }

            // Создаем запись в базе данных
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => 'test_notification',
                'title' => 'Тестовое уведомление',
                'message' => $message,
                'data' => [
                    'test_type' => $type,
                    'sent_at' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Тестовое уведомление отправлено',
                'data' => new NotificationResource($notification)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке тестового уведомления',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get notification by ID.
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            $notification = Notification::findOrFail($id);

            // Проверяем права доступа
            if ($notification->user_id && $notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Помечаем как прочитанное при просмотре
            if (!$notification->read_at) {
                $notification->update([
                    'read_at' => now(),
                    'read_by' => $user->id
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => new NotificationResource($notification)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Уведомление не найдено'
            ], 404);
        }
    }

    /**
     * Get latest notifications (for dashboard).
     */
    public function latest(Request $request)
    {
        try {
            $user = $request->user();
            $limit = $request->get('limit', 10);

            $notifications = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении последних уведомлений'
            ], 500);
        }
    }

    /**
     * Clear all notifications.
     */
    public function clearAll(Request $request)
    {
        try {
            $user = $request->user();

            $deleted = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })->delete();

            return response()->json([
                'success' => true,
                'message' => "$deleted уведомлений удалено",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Subscribe to push notifications.
     */
    public function subscribePush(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'endpoint' => 'required|string',
                'keys.auth' => 'required|string',
                'keys.p256dh' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Сохраняем подписку в базе данных
            $subscription = $user->pushSubscriptions()->create([
                'endpoint' => $request->endpoint,
                'public_key' => $request->keys['p256dh'],
                'auth_token' => $request->keys['auth'],
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Подписка на push-уведомления оформлена',
                'data' => $subscription
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при оформлении подписки',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Unsubscribe from push notifications.
     */
    public function unsubscribePush(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'endpoint' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $deleted = $user->pushSubscriptions()
                ->where('endpoint', $request->endpoint)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Подписка на push-уведомления отменена',
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отмене подписки',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper method to send test email.
     */
    private function sendTestEmail($user, $message)
    {
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                new \App\Mail\TestNotificationMail($user, $message)
            );
        } catch (\Exception $e) {
            throw new \Exception('Ошибка отправки email: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to send test push notification.
     */
    private function sendTestPush($user, $message)
    {
        try {
            // Используем Laravel Notifications с драйвером push
            $user->notify(new \App\Notifications\TestPushNotification($message));
        } catch (\Exception $e) {
            throw new \Exception('Ошибка отправки push: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to send test SMS.
     */
    private function sendTestSMS($user, $message)
    {
        try {
            // Проверяем наличие номера телефона
            if (!$user->phone) {
                throw new \Exception('Номер телефона не указан');
            }

            // Используем выбранный сервис SMS (например, Twilio)
            // $client = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
            // $client->messages->create(
            //     $user->phone,
            //     [
            //         'from' => config('services.twilio.from'),
            //         'body' => $message
            //     ]
            // );

            // Для теста просто логируем
            \Log::info("Test SMS to {$user->phone}: {$message}");

        } catch (\Exception $e) {
            throw new \Exception('Ошибка отправки SMS: ' . $e->getMessage());
        }
    }
}
