@extends('layouts.guest')

@section('title', 'Вход в систему')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h3 class="mb-0">
                            <i class="bi bi-door-open-fill me-2"></i>
                            Вход в систему
                        </h3>
                        <p class="mb-0 mt-2 small">Введите ваши учетные данные</p>
                    </div>

                    <div class="card-body p-5">
                        <form method="POST" action="{{ route('login') }}" id="loginForm">
                            @csrf

                            <!-- Email -->
                            <div class="form-group mb-4">
                                <label for="email" class="form-label fw-bold">
                                    <i class="bi bi-envelope-fill me-2"></i>Email адрес
                                </label>
                                <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                    <input id="email" type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           name="email" value="{{ old('email') }}"
                                           required autocomplete="email" autofocus
                                           placeholder="example@email.com">
                                    @error('email')
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Пароль -->
                            <div class="form-group mb-4">
                                <label for="password" class="form-label fw-bold">
                                    <i class="bi bi-key-fill me-2"></i>Пароль
                                </label>
                                <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                    <input id="password" type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           name="password" required
                                           autocomplete="current-password"
                                           placeholder="Введите пароль">
                                    <button class="btn btn-outline-secondary" type="button"
                                            id="togglePassword">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                    @error('password')
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Запомнить меня -->
                            <div class="form-group mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="remember" id="remember"
                                        {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="remember">
                                        <i class="bi bi-check-square me-1"></i> Запомнить меня
                                    </label>
                                </div>
                            </div>

                            <!-- Кнопка отправки -->
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Войти
                                </button>
                            </div>

                            <!-- Дополнительные ссылки -->
                            <div class="text-center">
                                @if (Route::has('password.request'))
                                    <a class="btn btn-link text-decoration-none"
                                       href="{{ route('password.request') }}">
                                        <i class="bi bi-question-circle me-1"></i>
                                        Забыли пароль?
                                    </a>
                                @endif
                            </div>

                            <!-- Разделитель -->
                            <div class="position-relative my-4">
                                <hr class="border-2">
                                <div class="position-absolute top-50 start-50 translate-middle bg-white px-3">
                                    <span class="text-muted">ИЛИ</span>
                                </div>
                            </div>

                            <!-- Регистрация -->
                            <div class="text-center">
                                <p class="mb-2">Нет аккаунта?</p>
                                <a href="{{ route('register') }}" class="btn btn-outline-success">
                                    <i class="bi bi-person-plus me-2"></i>
                                    Зарегистрироваться
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="card-footer text-center py-3 bg-light">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            Ваши данные защищены
                        </small>
                    </div>
                </div>

                <!-- Демо доступ -->
                <div class="card mt-4 border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-info">
                            <i class="bi bi-info-circle-fill me-2"></i>Демо доступ
                        </h6>
                        <p class="card-text small mb-2">
                            Для тестирования используйте:<br>
                            <strong>Админ:</strong> admin@example.com / password<br>
                            <strong>Пользователь:</strong> user@example.com / password
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .card {
            border-radius: 15px;
            overflow: hidden;
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border: none;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Показать/скрыть пароль
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);

                    if (eyeIcon) {
                        eyeIcon.classList.toggle('bi-eye');
                        eyeIcon.classList.toggle('bi-eye-slash');
                    }
                });
            }

            // Валидация формы
            const form = document.getElementById('loginForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const email = document.getElementById('email');
                    const password = document.getElementById('password');

                    if (!email.value || !password.value) {
                        e.preventDefault();
                        alert('Пожалуйста, заполните все поля');
                    }
                });
            }

            // Автофокус на email
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }
        });
    </script>
@endpush
