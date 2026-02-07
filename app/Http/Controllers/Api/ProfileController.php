<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\UpdateNotificationsRequest;
use App\Http\Requests\Api\VerifyPhoneRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Конструктор - применяем middleware аутентификации
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Получить профиль текущего пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load([
                'bookings' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(5);
                },
                'reviews' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(5);
                },
                'notifications' => function ($query) {
                    $query->where('read_at', null)->orderBy('created_at', 'desc')->limit(10);
                }
            ]);

            // Статистика пользователя
            $stats = [
                'total_bookings' => $user->bookings()->count(),
                'active_bookings' => $user->bookings()
                    ->whereIn('status', ['confirmed', 'active'])
                    ->count(),
                'total_reviews' => $user->reviews()->where('status', 'approved')->count(),
                'member_since' => $user->created_at->diffForHumans(),
                'verification_status' => $this->getVerificationStatus($user)
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => new UserResource($user),
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get profile error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении профиля',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Обновление профиля пользователя
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Если меняется email, сбрасываем подтверждение
            if (isset($data['email']) && $data['email'] !== $user->email) {
                $data['email_verified_at'] = null;

                // Отправляем email для подтверждения
                if (config('auth.verify_email', false)) {
                    $user->sendEmailVerificationNotification();
                    $data['email_verification_sent'] = true;
                }
            }

            // Если меняется телефон, сбрасываем подтверждение
            if (isset($data['phone']) && $data['phone'] !== $user->phone) {
                $data['phone_verified_at'] = null;
                $data['phone_verification_code'] = null;
            }

            // Обработка загрузки аватара
            if ($request->hasFile('avatar')) {
                // Удаляем старый аватар, если он есть
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $data['avatar'] = $avatarPath;
            }

            // Если пользователь хочет удалить аватар
            if ($request->has('remove_avatar') && $request->boolean('remove_avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $data['avatar'] = null;
            }

            // Обработка даты рождения
            if (isset($data['birth_date'])) {
                $data['birth_date'] = \Carbon\Carbon::parse($data['birth_date'])->format('Y-m-d');
            }

            $user->update($data);

            // Логируем изменение профиля
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'profile_update',
                'details' => json_encode(['fields' => array_keys($data)]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Профиль успешно обновлен',
                'data' => new UserResource($user->fresh())
            ]);

        } catch (\Exception $e) {
            \Log::error('Update profile error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении профиля',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Смена пароля
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Проверяем текущий пароль
            if (!Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Текущий пароль указан неверно'
                ], 422);
            }

            // Обновляем пароль
            $user->update([
                'password' => Hash::make($data['new_password'])
            ]);

            // Отправляем уведомление о смене пароля
            if ($user->email) {
                // Отправка email уведомления
                // \Illuminate\Support\Facades\Mail::to($user->email)->send(
                //     new \App\Mail\PasswordChanged($user)
                // );
            }

            // Логируем смену пароля
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'password_change',
                'details' => json_encode(['changed_at' => now()]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Пароль успешно изменен'
            ]);

        } catch (\Exception $e) {
            \Log::error('Change password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при смене пароля',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получить историю бронирований пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bookings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Booking::with(['room.hotel', 'payment'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Фильтрация по статусу
            if ($request->has('status')) {
                $statuses = explode(',', $request->input('status'));
                $query->whereIn('status', $statuses);
            }

            // Фильтрация по дате
            if ($request->has('date_from')) {
                $query->whereDate('check_in', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('check_out', '<=', $request->input('date_to'));
            }

            // Поиск по номеру бронирования или отелю
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('room.hotel', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Пагинация
            $perPage = $request->input('per_page', 15);
            $bookings = $query->paginate($perPage);

            // Статистика бронирований
            $stats = [
                'total' => $bookings->total(),
                'by_status' => Booking::where('user_id', $user->id)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'bookings' => BookingResource::collection($bookings),
                    'stats' => $stats,
                    'meta' => [
                        'current_page' => $bookings->currentPage(),
                        'last_page' => $bookings->lastPage(),
                        'per_page' => $bookings->perPage(),
                        'total' => $bookings->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get bookings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении истории бронирований',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получить отзывы пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reviews(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Review::with(['room', 'hotel', 'booking'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Фильтрация по статусу
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Фильтрация по рейтингу
            if ($request->has('rating')) {
                $query->where('rating', $request->input('rating'));
            }

            // Пагинация
            $perPage = $request->input('per_page', 15);
            $reviews = $query->paginate($perPage);

            // Статистика отзывов
            $stats = [
                'total' => $reviews->total(),
                'average_rating' => round($user->reviews()->avg('rating') ?? 0, 1),
                'by_status' => Review::where('user_id', $user->id)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'by_rating' => Review::where('user_id', $user->id)
                    ->selectRaw('rating, COUNT(*) as count')
                    ->groupBy('rating')
                    ->orderBy('rating', 'desc')
                    ->pluck('count', 'rating')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => ReviewResource::collection($reviews),
                    'stats' => $stats,
                    'meta' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get reviews error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении отзывов',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получить уведомления пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function notifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Фильтрация по типу
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            // Фильтрация по статусу прочтения
            if ($request->has('read')) {
                if ($request->boolean('read')) {
                    $query->whereNotNull('read_at');
                } else {
                    $query->whereNull('read_at');
                }
            }

            // Пагинация
            $perPage = $request->input('per_page', 20);
            $notifications = $query->paginate($perPage);

            // Статистика уведомлений
            $stats = [
                'total' => $notifications->total(),
                'unread' => $user->notifications()->whereNull('read_at')->count(),
                'by_type' => Notification::where('user_id', $user->id)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => NotificationResource::collection($notifications),
                    'stats' => $stats,
                    'meta' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get notifications error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Отметить уведомление как прочитанное
     *
     * @param Request $request
     * @param Notification $notification
     * @return JsonResponse
     */
    public function markNotificationAsRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли уведомление пользователю
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Отмечаем как прочитанное
            if (!$notification->read_at) {
                $notification->update(['read_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Уведомление отмечено как прочитанное',
                'data' => new NotificationResource($notification)
            ]);

        } catch (\Exception $e) {
            \Log::error('Mark notification as read error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отметке уведомления',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Отметить все уведомления как прочитанные
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = $user->notifications()
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => "{$count} уведомлений отмечены как прочитанные",
                'data' => ['marked_count' => $count]
            ]);

        } catch (\Exception $e) {
            \Log::error('Mark all notifications as read error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отметке уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Удалить уведомление
     *
     * @param Request $request
     * @param Notification $notification
     * @return JsonResponse
     */
    public function deleteNotification(Request $request, Notification $notification): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли уведомление пользователю
            if ($notification->user_id !== $user->id) {
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
            \Log::error('Delete notification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении уведомления',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получить настройки уведомлений
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function notificationSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $settings = $user->notification_settings ?? [];

            if (is_string($settings)) {
                $settings = json_decode($settings, true) ?? [];
            }

            // Настройки по умолчанию
            $defaultSettings = [
                'email' => [
                    'booking_confirmation' => true,
                    'booking_cancellation' => true,
                    'payment_confirmation' => true,
                    'reminders' => true,
                    'special_offers' => true,
                    'newsletter' => false,
                ],
                'push' => [
                    'booking_updates' => true,
                    'promotions' => false,
                    'reminders' => true,
                ],
                'sms' => [
                    'booking_confirmation' => false,
                    'reminders' => false,
                ]
            ];

            $settings = array_merge($defaultSettings, $settings);

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            \Log::error('Get notification settings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении настроек уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Обновить настройки уведомлений
     *
     * @param UpdateNotificationsRequest $request
     * @return JsonResponse
     */
    public function updateNotificationSettings(UpdateNotificationsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $settings = $request->validated();

            // Сохраняем настройки
            $user->update([
                'notification_settings' => json_encode($settings)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Настройки уведомлений обновлены',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            \Log::error('Update notification settings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении настроек уведомлений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Отправить код верификации телефона
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendPhoneVerification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Номер телефона не указан'
                ], 400);
            }

            if ($user->phone_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Телефон уже подтвержден'
                ], 400);
            }

            // Генерируем код верификации (6 цифр)
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Сохраняем код
            $user->update([
                'phone_verification_code' => $verificationCode,
                'phone_verification_sent_at' => now()
            ]);

            // Отправляем SMS (заглушка)
            // $this->sendSms($user->phone, "Ваш код подтверждения: {$verificationCode}");

            // Для тестирования возвращаем код в ответе
            $returnCode = config('app.debug') ? $verificationCode : null;

            return response()->json([
                'success' => true,
                'message' => 'Код подтверждения отправлен',
                'data' => [
                    'code' => $returnCode,
                    'expires_in' => 300 // 5 минут
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Send phone verification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке кода подтверждения',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Подтвердить телефон
     *
     * @param VerifyPhoneRequest $request
     * @return JsonResponse
     */
    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            if (!$user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Номер телефона не указан'
                ], 400);
            }

            if ($user->phone_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Телефон уже подтвержден'
                ], 400);
            }

            // Проверяем код
            if ($user->phone_verification_code !== $data['code']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный код подтверждения'
                ], 422);
            }

            // Проверяем срок действия кода (5 минут)
            if ($user->phone_verification_sent_at < now()->subMinutes(5)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Срок действия кода истек'
                ], 422);
            }

            // Подтверждаем телефон
            $user->update([
                'phone_verified_at' => now(),
                'phone_verification_code' => null,
                'phone_verification_sent_at' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Телефон успешно подтвержден',
                'data' => [
                    'verified_at' => $user->phone_verified_at
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Verify phone error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подтверждении телефона',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Удалить аккаунт
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'confirmation' => 'required|string|in:DELETE MY ACCOUNT'
            ]);

            // Проверяем активные бронирования
            $activeBookings = $user->bookings()
                ->whereIn('status', ['pending', 'confirmed', 'active'])
                ->exists();

            if ($activeBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить аккаунт с активными бронированиями'
                ], 400);
            }

            // Архивируем данные пользователя
            $this->archiveUserData($user);

            // Удаляем аккаунт
            $user->delete();

            // Выход из системы
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Аккаунт успешно удален'
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete account error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении аккаунта',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * История действий пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activityLog(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = \App\Models\AuditLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Фильтрация по действию
            if ($request->has('action')) {
                $query->where('action', $request->input('action'));
            }

            // Фильтрация по дате
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            // Пагинация
            $perPage = $request->input('per_page', 20);
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs->items(),
                    'meta' => [
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get activity log error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении истории действий',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получить статус верификации пользователя
     *
     * @param User $user
     * @return array
     */
    private function getVerificationStatus(User $user): array
    {
        return [
            'email' => (bool) $user->email_verified_at,
            'phone' => (bool) $user->phone_verified_at,
            'identity' => (bool) $user->identity_verified_at,
            'level' => $this->calculateVerificationLevel($user)
        ];
    }

    /**
     * Рассчитать уровень верификации
     *
     * @param User $user
     * @return int
     */
    private function calculateVerificationLevel(User $user): int
    {
        $level = 0;

        if ($user->email_verified_at) $level++;
        if ($user->phone_verified_at) $level++;
        if ($user->identity_verified_at) $level++;

        return $level;
    }

    /**
     * Архивировать данные пользователя перед удалением
     *
     * @param User $user
     * @return void
     */
    private function archiveUserData(User $user): void
    {
        try {
            $archiveData = [
                'user' => $user->toArray(),
                'bookings' => $user->bookings()->get()->toArray(),
                'reviews' => $user->reviews()->get()->toArray(),
                'payments' => $user->payments()->get()->toArray(),
                'deleted_at' => now()
            ];

            // Сохраняем архив
            \App\Models\UserArchive::create([
                'original_user_id' => $user->id,
                'email' => $user->email,
                'data' => json_encode($archiveData, JSON_UNESCAPED_UNICODE)
            ]);

        } catch (\Exception $e) {
            \Log::error('Archive user data error: ' . $e->getMessage());
        }
    }
}
