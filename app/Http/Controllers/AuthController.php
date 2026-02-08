<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Maximum number of login attempts before throttling.
     */
    protected $maxAttempts = 5;

    /**
     * Number of minutes to throttle after max attempts.
     */
    protected $decayMinutes = 15;

    /**
     * Show login form.
     */
    public function showLoginForm()
    {
        // Если пользователь уже авторизован, редиректим в личный кабинет
        if (Auth::check()) {
            return redirect()->route('profile.index');
        }

        return view('auth.login');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request)
    {
        // Проверяем ограничение скорости
        $this->checkRateLimit($request);

        $credentials = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'remember' => 'nullable|boolean',
        ]);

        // Попытка аутентификации
        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password']
        ], $credentials['remember'] ?? false)) {

            $user = Auth::user();

            // Проверяем статус пользователя
            if ($user->status === 'inactive') {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Ваш аккаунт деактивирован. Обратитесь в поддержку.'
                ]);
            }

            // Проверяем бан
            if ($user->isBanned()) {
                Auth::logout();
                $bannedUntil = $user->banned_until
                    ? $user->banned_until->format('d.m.Y H:i')
                    : 'навсегда';

                return back()->withErrors([
                    'email' => "Ваш аккаунт заблокирован до {$bannedUntil}. Причина: {$user->ban_reason}"
                ]);
            }

            // Обновляем время последнего входа
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Сбрасываем счетчик неудачных попыток
            RateLimiter::clear($this->throttleKey($request));

            // Редирект в зависимости от роли
            if ($user->isAdmin() || $user->isModerator()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('profile.index'))
                ->with('success', 'Добро пожаловать, ' . $user->name . '!');
        }

        // Увеличиваем счетчик неудачных попыток
        RateLimiter::hit($this->throttleKey($request), $this->decayMinutes * 60);

        // Если осталась 1 попытка - предупреждаем
        $remaining = RateLimiter::remaining($this->throttleKey($request), $this->maxAttempts);
        if ($remaining === 1) {
            return back()->withErrors([
                'email' => 'Неверные учетные данные. Осталась 1 попытка. После этого аккаунт будет заблокирован на 15 минут.'
            ]);
        }

        return back()->withErrors([
            'email' => 'Неверные email или пароль. Осталось попыток: ' . $remaining
        ]);
    }

    /**
     * Show registration form.
     */
    public function showRegistrationForm()
    {
        // Если пользователь уже авторизован, редиректим
        if (Auth::check()) {
            return redirect()->route('profile.index');
        }

        return view('auth.register');
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'agree_terms' => 'required|accepted',
            'marketing_consent' => 'nullable|boolean',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
        ]);

        // Создаем пользователя
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active',
            'country' => $validated['country'],
            'city' => $validated['city'],
            'marketing_consent' => $validated['marketing_consent'] ?? false,
            'registration_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Отправляем событие регистрации
        event(new Registered($user));

        // Авторизуем пользователя
        Auth::login($user);

        // Отправляем приветственное письмо
        // Mail::to($user)->send(new WelcomeEmail($user));

        // Редирект на страницу подтверждения email
        return redirect()->route('verification.notice')
            ->with('success', 'Регистрация успешна! Пожалуйста, подтвердите ваш email.');
    }

    /**
     * Show forgot password form.
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Проверяем, не забанен ли пользователь
        $user = User::where('email', $request->email)->first();
        if ($user && $user->isBanned()) {
            return back()->withErrors([
                'email' => 'Аккаунт заблокирован. Восстановление пароля невозможно.'
            ]);
        }

        // Отправляем ссылку для сброса пароля
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * Show reset password form.
     */
    public function showResetPasswordForm(Request $request)
    {
        return view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->email,
        ]);
    }

    /**
     * Handle reset password request.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Сбрасываем пароль
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * Show email verification notice.
     */
    public function showVerificationNotice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('profile.index');
        }

        return view('auth.verify-email');
    }

    /**
     * Handle email verification.
     */
    public function verifyEmail(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('profile.index')
                ->with('info', 'Email уже подтвержден.');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('profile.index')
            ->with('success', 'Email успешно подтвержден!');
    }

    /**
     * Resend verification email.
     */
    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('profile.index')
                ->with('info', 'Email уже подтвержден.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Ссылка для подтверждения отправлена на ваш email.');
    }

    /**
     * Show change password form.
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Handle change password request.
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        // Проверяем текущий пароль
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Текущий пароль неверен.'
            ]);
        }

        // Обновляем пароль
        $user->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        // Отправляем уведомление
        // Notification::send($user, new PasswordChanged($user));

        return back()->with('success', 'Пароль успешно изменен.');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        // Запоминаем предыдущую страницу (кроме админки)
        $previousUrl = url()->previous();
        $isAdminRoute = str_contains($previousUrl, '/admin/');

        // Логируем выход
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('User logout', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Редиректим на предыдущую страницу (если это не админка)
        if (!$isAdminRoute && !str_contains($previousUrl, '/login')) {
            return redirect($previousUrl)->with('success', 'Вы успешно вышли из системы.');
        }

        return redirect()->route('home')
            ->with('success', 'Вы успешно вышли из системы.');
    }

    /**
     * Check rate limit for login attempts.
     */
    protected function checkRateLimit(Request $request)
    {
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Слишком много попыток входа. Попробуйте снова через " . ceil($seconds / 60) . " минут."
            ])->status(429);
        }
    }

    /**
     * Get throttle key for rate limiting.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')) . '|' . $request->ip();
    }

    /**
     * Show OAuth login options.
     */
    public function showOAuthOptions()
    {
        return view('auth.oauth');
    }

    /**
     * Redirect to OAuth provider.
     */
    public function redirectToProvider(string $provider)
    {
        $validProviders = ['google', 'facebook', 'github', 'vkontakte'];

        if (!in_array($provider, $validProviders)) {
            abort(404);
        }

        // Здесь будет редирект на провайдера OAuth
        // return Socialite::driver($provider)->redirect();

        return back()->with('info', 'OAuth авторизация через ' . $provider . ' временно недоступна.');
    }

    /**
     * Handle OAuth callback.
     */
    public function handleProviderCallback(string $provider)
    {
        try {
            // $socialUser = Socialite::driver($provider)->user();

            // // Поиск или создание пользователя
            // $user = User::where('email', $socialUser->getEmail())->first();

            // if (!$user) {
            //     $user = User::create([
            //         'name' => $socialUser->getName(),
            //         'email' => $socialUser->getEmail(),
            //         'password' => Hash::make(Str::random(40)),
            //         'email_verified_at' => now(),
            //         'oauth_provider' => $provider,
            //         'oauth_id' => $socialUser->getId(),
            //     ]);
            // }

            // Auth::login($user);

            // return redirect()->route('profile.index')
            //     ->with('success', 'Вход через ' . $provider . ' выполнен успешно!');

            return back()->with('info', 'OAuth авторизация через ' . $provider . ' временно недоступна.');
        } catch (\Exception $e) {
            Log::error('OAuth error: ' . $e->getMessage());

            return redirect()->route('login')
                ->withErrors(['error' => 'Ошибка авторизации через ' . $provider . '. Пожалуйста, попробуйте другой способ.']);
        }
    }

    /**
     * Delete account request.
     */
    public function showDeleteAccountForm()
    {
        return view('auth.delete-account');
    }

    /**
     * Handle account deletion.
     */
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'password' => 'required|string|current_password',
            'reason' => 'nullable|string|max:500',
            'delete_all_data' => 'nullable|boolean',
        ]);

        // Проверяем активные бронирования
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->count();

        if ($activeBookings > 0) {
            return back()->withErrors([
                'error' => 'Нельзя удалить аккаунт с активными бронированиями. '
                    . 'Сначала отмените или завершите все бронирования.'
            ]);
        }

        // Логируем удаление
        Log::info('Account deletion requested', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reason' => $validated['reason'],
            'delete_all_data' => $validated['delete_all_data'] ?? false,
        ]);

        // Анонимизируем или удаляем данные
        if ($request->has('delete_all_data') && $request->delete_all_data) {
            // Удаляем все данные пользователя
            $this->deleteUserData($user);
            $user->delete();
        } else {
            // Анонимизируем данные (GDPR compliance)
            $this->anonymizeUserData($user);
        }

        // Выход из системы
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Ваш аккаунт успешно удален. Надеемся увидеть вас снова!');
    }

    /**
     * Anonymize user data.
     */
    private function anonymizeUserData(User $user): void
    {
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@example.com',
            'phone' => null,
            'password' => Hash::make(Str::random(40)),
            'country' => null,
            'city' => null,
            'address' => null,
            'postal_code' => null,
            'date_of_birth' => null,
            'gender' => null,
            'preferences' => [],
            'notes' => null,
            'telegram_chat_id' => null,
            'notification_preferences' => [],
            'marketing_consent' => false,
            'status' => 'inactive',
            'deleted_at' => now(),
            'anonymized_at' => now(),
        ]);
    }

    /**
     * Delete all user data.
     */
    private function deleteUserData(User $user): void
    {
        // Удаляем связанные данные
        $user->bookings()->delete();
        $user->reviews()->delete();
        $user->notifications()->delete();
        $user->payments()->delete();

        // Удаляем пользователя (каскадно удалит остальное)
        $user->forceDelete();
    }

    /**
     * Show two-factor authentication setup.
     */
    public function showTwoFactorForm()
    {
        if (!config('auth.two_factor_enabled')) {
            abort(404);
        }

        $user = Auth::user();

        // Генерируем секрет для 2FA, если его нет
        if (!$user->two_factor_secret) {
            // $user->two_factor_secret = encrypt(app('pragmarx.google2fa')->generateSecretKey());
            // $user->save();
        }

        // $qrCode = app('pragmarx.google2fa')->getQRCodeInline(
        //     config('app.name'),
        //     $user->email,
        //     decrypt($user->two_factor_secret)
        // );

        return view('auth.two-factor', [
            // 'qrCode' => $qrCode,
            // 'secret' => decrypt($user->two_factor_secret),
            'enabled' => $user->two_factor_enabled,
        ]);
    }

    /**
     * Enable two-factor authentication.
     */
    public function enableTwoFactor(Request $request)
    {
        if (!config('auth.two_factor_enabled')) {
            abort(404);
        }

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        // Проверяем код
        // $valid = app('pragmarx.google2fa')->verifyKey(
        //     decrypt($user->two_factor_secret),
        //     $validated['code']
        // );

        // if (!$valid) {
        //     return back()->withErrors(['code' => 'Неверный код подтверждения.']);
        // }

        // Включаем 2FA
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_enabled_at' => now(),
        ]);

        // Генерируем резервные коды
        // $recoveryCodes = $user->generateTwoFactorRecoveryCodes();
        // $user->update(['two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes))]);

        return redirect()->route('auth.two-factor.show')
            ->with('success', 'Двухфакторная аутентификация успешно включена.')
            ->with('recoveryCodes', []); // $recoveryCodes
    }

    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = Auth::user();

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ]);

        return back()->with('success', 'Двухфакторная аутентификация отключена.');
    }

    /**
     * Show recovery codes.
     */
    public function showRecoveryCodes()
    {
        $user = Auth::user();

        if (!$user->two_factor_enabled) {
            return redirect()->route('auth.two-factor.show');
        }

        // $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes));

        return view('auth.recovery-codes', [
            'recoveryCodes' => [], // $recoveryCodes
        ]);
    }

    /**
     * Generate new recovery codes.
     */
    public function generateNewRecoveryCodes(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = Auth::user();

        // $recoveryCodes = $user->generateTwoFactorRecoveryCodes();
        // $user->update(['two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes))]);

        return back()->with('success', 'Новые коды восстановления сгенерированы.')
            ->with('recoveryCodes', []); // $recoveryCodes
    }

    /**
     * Check if email exists (AJAX).
     */
    public function checkEmailExists(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Email уже зарегистрирован' : 'Email свободен',
        ]);
    }

    /**
     * Check if phone exists (AJAX).
     */
    public function checkPhoneExists(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $exists = User::where('phone', $request->phone)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Телефон уже зарегистрирован' : 'Телефон свободен',
        ]);
    }

    /**
     * Show session expired page.
     */
    public function sessionExpired()
    {
        return view('auth.session-expired');
    }

    /**
     * Show account locked page.
     */
    public function accountLocked()
    {
        return view('auth.account-locked');
    }

    /**
     * Show maintenance mode page.
     */
    public function maintenance()
    {
        if (!app()->isDownForMaintenance()) {
            return redirect()->route('home');
        }

        return view('auth.maintenance');
    }

    /**
     * Показать форму регистрации
     */
    public function showRegisterForm()
    {
        return view('auth.register');
    }
}
