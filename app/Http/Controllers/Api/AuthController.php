<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\VerifyEmailRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Регистрация нового пользователя
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']);

            // Создаем пользователя
            $user = User::create($data);

            // Генерируем токен
            $token = $user->createToken('auth_token')->plainTextToken;

            // Отправляем событие регистрации
            event(new Registered($user));

            // Отправляем email подтверждения, если требуется
            if (config('auth.verify_email', false)) {
                $user->sendEmailVerificationNotification();
            }

            return response()->json([
                'success' => true,
                'message' => 'Регистрация успешно завершена',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при регистрации пользователя',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Авторизация пользователя
     *
     * @param LoginRequest $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();

            // Проверяем учетные данные
            if (!Auth::attempt($credentials)) {
                throw ValidationException::withMessages([
                    'email' => ['Неверный email или пароль'],
                ]);
            }

            $user = User::where('email', $request->email)->firstOrFail();

            // Проверяем, подтвержден ли email
            if (config('auth.verify_email', false) && !$user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email не подтвержден',
                    'requires_verification' => true
                ], 403);
            }

            // Проверяем, не заблокирован ли пользователь
            if ($user->status === 'banned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ваш аккаунт заблокирован'
                ], 403);
            }

            if ($user->status === 'inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ваш аккаунт деактивирован'
                ], 403);
            }

            // Генерируем токен
            $token = $user->createToken('auth_token')->plainTextToken;

            // Обновляем последний вход
            $user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Авторизация успешна',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при авторизации',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Выход пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Удаляем текущий токен
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Успешный выход из системы'
            ]);

        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при выходе из системы'
            ], 500);
        }
    }

    /**
     * Получить информацию о текущем пользователе
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load(['bookings', 'reviews', 'notifications']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            \Log::error('Get user error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных пользователя'
            ], 500);
        }
    }

    /**
     * Обновление профиля пользователя
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
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
                }
            }

            // Обработка загрузки аватара
            if ($request->hasFile('avatar')) {
                // Удаляем старый аватар, если он есть
                if ($user->avatar) {
                    \Storage::disk('public')->delete($user->avatar);
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $data['avatar'] = $path;
            }

            $user->update($data);

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

            // Удаляем все токены пользователя (выход со всех устройств)
            $user->tokens()->delete();

            // Создаем новый токен
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Пароль успешно изменен',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
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
     * Запрос на восстановление пароля
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ссылка для восстановления пароля отправлена на email'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить ссылку для восстановления пароля'
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Forgot password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при запросе восстановления пароля'
            ], 500);
        }
    }

    /**
     * Сброс пароля
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Пароль успешно сброшен'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось сбросить пароль',
                'status' => $status
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Reset password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при сбросе пароля'
            ], 500);
        }
    }

    /**
     * Подтверждение email
     *
     * @param VerifyEmailRequest $request
     * @return JsonResponse
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        try {
            $user = User::findOrFail($request->input('id'));

            // Проверяем хэш
            if (!hash_equals(
                (string) $request->input('hash'),
                sha1($user->getEmailForVerification())
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверная ссылка подтверждения'
                ], 403);
            }

            // Проверяем, не подтвержден ли уже email
            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email уже подтвержден'
                ], 400);
            }

            // Подтверждаем email
            $user->markEmailAsVerified();

            // Создаем токен для автоматического входа
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email успешно подтвержден',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Verify email error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подтверждении email'
            ], 500);
        }
    }

    /**
     * Повторная отправка email для подтверждения
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $request->email)->firstOrFail();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email уже подтвержден'
                ], 400);
            }

            $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'Письмо с подтверждением отправлено'
            ]);

        } catch (\Exception $e) {
            \Log::error('Resend verification email error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке письма подтверждения'
            ], 500);
        }
    }

    /**
     * Обновление токена (refresh token)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Удаляем текущий токен
            $request->user()->currentAccessToken()->delete();

            // Создаем новый токен
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Токен обновлен',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', 525600) * 60
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Refresh token error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении токена'
            ], 500);
        }
    }

    /**
     * Выход со всех устройств
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Удаляем все токены пользователя
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Выполнен выход со всех устройств'
            ]);

        } catch (\Exception $e) {
            \Log::error('Logout all error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при выходе со всех устройств'
            ], 500);
        }
    }

    /**
     * Получить список активных сессий
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $sessions = $user->tokens()
                ->select(['id', 'name', 'last_used_at', 'created_at', 'expires_at'])
                ->orderBy('last_used_at', 'desc')
                ->get()
                ->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'name' => $token->name,
                        'last_used' => $token->last_used_at ? $token->last_used_at->diffForHumans() : null,
                        'created_at' => $token->created_at->format('d.m.Y H:i'),
                        'expires_at' => $token->expires_at ? $token->expires_at->format('d.m.Y H:i') : null,
                        'is_current' => $token->id === $request->user()->currentAccessToken()->id
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $sessions
            ]);

        } catch (\Exception $e) {
            \Log::error('Get sessions error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка сессий'
            ], 500);
        }
    }

    /**
     * Завершить конкретную сессию
     *
     * @param Request $request
     * @param string $tokenId
     * @return JsonResponse
     */
    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        try {
            $user = $request->user();

            // Находим токен
            $token = $user->tokens()->find($tokenId);

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сессия не найдена'
                ], 404);
            }

            // Нельзя завершить текущую сессию через этот метод
            if ($token->id === $request->user()->currentAccessToken()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя завершить текущую сессию. Используйте метод logout.'
                ], 400);
            }

            $token->delete();

            return response()->json([
                'success' => true,
                'message' => 'Сессия завершена'
            ]);

        } catch (\Exception $e) {
            \Log::error('Revoke session error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при завершении сессии'
            ], 500);
        }
    }
}
