@extends('layouts.guest')

@section('title', 'Сброс пароля')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-info text-white text-center py-4">
                        <h3 class="mb-0">
                            <i class="bi bi-key-fill me-2"></i>
                            Создание нового пароля
                        </h3>
                        <p class="mb-0 mt-2 small">Введите новый пароль для вашего аккаунта</p>
                    </div>

                    <div class="card-body p-5">
                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    Ошибка валидации
                                </h6>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.update') }}" id="resetPasswordForm">
                            @csrf

                            <!-- Скрытые поля -->
                            <input type="hidden" name="token" value="{{ $token }}">

                            <!-- Описание -->
                            <div class="text-center mb-4">
                                <div class="mb-3">
                                    <i class="bi bi-shield-lock-fill text-info fs-1"></i>
                                </div>
                                <p class="text-muted">
                                    Создайте новый надежный пароль для вашего аккаунта
                                    <strong>{{ $email ?? old('email') }}</strong>
                                </p>
                            </div>

                            <!-- Email (скрытое) -->
                            <div class="form-group mb-4 d-none">
                                <label for="email" class="form-label fw-bold">
                                    <i class="bi bi-envelope-fill me-2"></i>Email адрес
                                </label>
                                <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                    <input id="email" type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           name="email" value="{{ $email ?? old('email') }}"
                                           required autocomplete="email">
                                    @error('email')
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Новый пароль -->
                            <div class="form-group mb-4">
                                <label for="password" class="form-label fw-bold">
                                    <i class="bi bi-lock-fill me-2"></i>Новый пароль
                                </label>
                                <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-key"></i>
                                </span>
                                    <input id="password" type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           name="password" required
                                           autocomplete="new-password"
                                           placeholder="Минимум 8 символов">
                                    <button class="btn btn-outline-secondary" type="button"
                                            onclick="togglePassword('password', 'togglePasswordIcon')">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                    @error('password')
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <!-- Индикатор сложности пароля -->
                                <div class="password-strength mt-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="form-text">Сложность пароля:</small>
                                        <small class="form-text" id="passwordStrengthText">Слабый</small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-danger" id="passwordStrengthBar"
                                             role="progressbar" style="width: 20%"></div>
                                    </div>
                                    <small class="form-text text-muted mt-1" id="passwordHint">
                                        Используйте буквы, цифры и специальные символы
                                    </small>
                                </div>
                            </div>

                            <!-- Подтверждение пароля -->
                            <div class="form-group mb-4">
                                <label for="password_confirmation" class="form-label fw-bold">
                                    <i class="bi bi-lock-fill me-2"></i>Подтвердите пароль
                                </label>
                                <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-key-fill"></i>
                                </span>
                                    <input id="password_confirmation" type="password"
                                           class="form-control"
                                           name="password_confirmation" required
                                           autocomplete="new-password"
                                           placeholder="Повторите новый пароль">
                                    <button class="btn btn-outline-secondary" type="button"
                                            onclick="togglePassword('password_confirmation', 'toggleConfirmIcon')">
                                        <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                                    </button>
                                </div>
                                <small class="form-text" id="passwordMatch"></small>
                            </div>

                            <!-- Требования к паролю -->
                            <div class="card border-info mb-4">
                                <div class="card-body p-3">
                                    <h6 class="card-title text-info mb-2">
                                        <i class="bi bi-list-check me-2"></i>Требования к паролю:
                                    </h6>
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="mb-1">
                                            <i class="bi bi-check-circle text-success" id="lengthCheck"></i>
                                            <span class="ms-2">Минимум 8 символов</span>
                                        </li>
                                        <li class="mb-1">
                                            <i class="bi bi-check-circle text-muted" id="uppercaseCheck"></i>
                                            <span class="ms-2">Хотя бы одна заглавная буква</span>
                                        </li>
                                        <li class="mb-1">
                                            <i class="bi bi-check-circle text-muted" id="lowercaseCheck"></i>
                                            <span class="ms-2">Хотя бы одна строчная буква</span>
                                        </li>
                                        <li class="mb-1">
                                            <i class="bi bi-check-circle text-muted" id="numberCheck"></i>
                                            <span class="ms-2">Хотя бы одна цифра</span>
                                        </li>
                                        <li>
                                            <i class="bi bi-check-circle text-muted" id="specialCheck"></i>
                                            <span class="ms-2">Хотя бы один специальный символ (!@#$%^&*)</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Кнопка отправки -->
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-info btn-lg" id="submitBtn">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    Установить новый пароль
                                </button>
                            </div>

                            <!-- Дополнительные ссылки -->
                            <div class="text-center">
                                <p class="mb-2">Вспомнили старый пароль?</p>
                                <a href="{{ route('login') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Вернуться ко входу
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="card-footer text-center py-3 bg-light">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            После сброса пароля рекомендуется выйти из всех устройств
                        </small>
                    </div>
                </div>

                <!-- Время действия токена -->
                <div class="card mt-4 border-warning">
                    <div class="card-body text-center">
                        <h6 class="card-title text-warning mb-2">
                            <i class="bi bi-clock-history me-2"></i>Время действия ссылки
                        </h6>
                        <p class="card-text small mb-2">
                            Ссылка для сброса пароля действительна в течение
                            <strong>60 минут</strong>. Если время истекло,
                            <a href="{{ route('password.request') }}" class="text-decoration-none">
                                запросите новую ссылку
                            </a>.
                        </p>
                        <div class="mt-2" id="tokenTimer">
                            <!-- Таймер будет добавлен через JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .card {
            border-radius: 20px;
            overflow: hidden;
        }
        .card-header {
            border-radius: 20px 20px 0 0 !important;
        }
        .btn-info {
            background: linear-gradient(135deg, #0dcaf0 0%, #0bb5d6 100%);
            border: none;
            color: #fff;
            font-weight: 600;
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #0bb5d6 0%, #0aa0c2 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 202, 240, 0.3);
        }
        .progress {
            border-radius: 3px;
        }
        .password-strength .progress-bar {
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        .bi-check-circle {
            transition: color 0.3s ease;
        }
    </style>
@endpush

@push('scripts')
    <script>
        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('password_confirmation');
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            const matchHint = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');

            // Элементы проверки требований
            const lengthCheck = document.getElementById('lengthCheck');
            const uppercaseCheck = document.getElementById('uppercaseCheck');
            const lowercaseCheck = document.getElementById('lowercaseCheck');
            const numberCheck = document.getElementById('numberCheck');
            const specialCheck = document.getElementById('specialCheck');

            // Проверка сложности пароля
            password.addEventListener('input', function() {
                const pass = this.value;
                let strength = 0;
                let text = '';
                let color = 'bg-danger';

                // Проверки
                const hasLength = pass.length >= 8;
                const hasUppercase = /[A-Z]/.test(pass);
                const hasLowercase = /[a-z]/.test(pass);
                const hasNumber = /\d/.test(pass);
                const hasSpecial = /[!@#$%^&*]/.test(pass);

                // Обновление иконок проверки
                updateCheckIcon(lengthCheck, hasLength);
                updateCheckIcon(uppercaseCheck, hasUppercase);
                updateCheckIcon(lowercaseCheck, hasLowercase);
                updateCheckIcon(numberCheck, hasNumber);
                updateCheckIcon(specialCheck, hasSpecial);

                // Подсчет сложности
                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasLowercase) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;

                // Определение уровня сложности
                switch(strength) {
                    case 0:
                    case 1:
                        text = 'Очень слабый';
                        color = 'bg-danger';
                        break;
                    case 2:
                        text = 'Слабый';
                        color = 'bg-danger';
                        break;
                    case 3:
                        text = 'Средний';
                        color = 'bg-warning';
                        break;
                    case 4:
                        text = 'Хороший';
                        color = 'bg-info';
                        break;
                    case 5:
                        text = 'Отличный!';
                        color = 'bg-success';
                        break;
                }

                // Обновление интерфейса
                strengthBar.style.width = (strength * 20) + '%';
                strengthBar.className = 'progress-bar ' + color;
                strengthText.textContent = text;
                strengthText.className = 'form-text ' +
                    (color === 'bg-danger' ? 'text-danger' :
                        color === 'bg-warning' ? 'text-warning' :
                            color === 'bg-info' ? 'text-info' : 'text-success');

                // Проверка совпадения паролей
                checkPasswordMatch();
            });

            // Функция обновления иконки проверки
            function updateCheckIcon(element, isValid) {
                if (isValid) {
                    element.className = 'bi bi-check-circle-fill text-success';
                } else {
                    element.className = 'bi bi-circle text-muted';
                }
            }

            // Проверка совпадения паролей
            function checkPasswordMatch() {
                if (password.value && confirmPassword.value) {
                    if (password.value === confirmPassword.value) {
                        matchHint.textContent = '✓ Пароли совпадают';
                        matchHint.className = 'form-text text-success';
                        submitBtn.disabled = false;
                    } else {
                        matchHint.textContent = '✗ Пароли не совпадают';
                        matchHint.className = 'form-text text-danger';
                        submitBtn.disabled = true;
                    }
                } else {
                    matchHint.textContent = '';
                    submitBtn.disabled = !password.value;
                }
            }

            confirmPassword.addEventListener('input', checkPasswordMatch);

            // Таймер действия токена (пример на 60 минут)
            const tokenTimer = document.getElementById('tokenTimer');
            if (tokenTimer) {
                // Установите время истечения токена (60 минут от текущего времени)
                const expiresAt = new Date();
                expiresAt.setMinutes(expiresAt.getMinutes() + 60);

                function updateTimer() {
                    const now = new Date();
                    const diff = expiresAt - now;

                    if (diff <= 0) {
                        tokenTimer.innerHTML = `
                        <div class="alert alert-danger p-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Ссылка истекла! Запросите новую.
                        </div>
                    `;
                        submitBtn.disabled = true;
                        return;
                    }

                    const minutes = Math.floor(diff / 1000 / 60);
                    const seconds = Math.floor((diff / 1000) % 60);

                    tokenTimer.innerHTML = `
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="text-center mx-3">
                            <div class="fs-4 fw-bold text-warning">${minutes}</div>
                            <small class="text-muted">минут</small>
                        </div>
                        <div class="text-center mx-3">
                            <div class="fs-4 fw-bold text-warning">${seconds}</div>
                            <small class="text-muted">секунд</small>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        Осталось до истечения ссылки
                    </small>
                `;
                }

                updateTimer();
                setInterval(updateTimer, 1000);
            }

            // Валидация формы при отправке
            const form = document.getElementById('resetPasswordForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const pass = password.value;

                    if (pass.length < 8) {
                        e.preventDefault();
                        showAlert('Пароль должен содержать минимум 8 символов', 'danger');
                        password.focus();
                        return;
                    }

                    if (!/[A-Z]/.test(pass)) {
                        e.preventDefault();
                        showAlert('Пароль должен содержать хотя бы одну заглавную букву', 'danger');
                        password.focus();
                        return;
                    }

                    if (!/[a-z]/.test(pass)) {
                        e.preventDefault();
                        showAlert('Пароль должен содержать хотя бы одну строчную букву', 'danger');
                        password.focus();
                        return;
                    }

                    if (!/\d/.test(pass)) {
                        e.preventDefault();
                        showAlert('Пароль должен содержать хотя бы одну цифру', 'danger');
                        password.focus();
                        return;
                    }

                    if (password.value !== confirmPassword.value) {
                        e.preventDefault();
                        showAlert('Пароли не совпадают', 'danger');
                        confirmPassword.focus();
                        return;
                    }
                });
            }

            // Функция показа уведомлений
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

                const form = document.getElementById('resetPasswordForm');
                if (form) {
                    form.insertBefore(alertDiv, form.firstChild);

                    setTimeout(() => {
                        alertDiv.remove();
                    }, 5000);
                }
            }

            // Автофокус на поле пароля
            if (password) {
                password.focus();
            }
        });
    </script>
@endpush
