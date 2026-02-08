@extends('layouts.guest')

@section('title', 'Регистрация')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0">
                            <i class="bi bi-person-plus-fill me-2"></i>
                            Создание аккаунта
                        </h3>
                        <p class="mb-0 mt-2 small">Заполните форму для регистрации</p>
                    </div>

                    <div class="card-body p-5">
                        <form method="POST" action="{{ route('register') }}" id="registerForm">
                            @csrf

                            <div class="row">
                                <!-- Имя -->
                                <div class="col-md-6 mb-4">
                                    <label for="name" class="form-label fw-bold">
                                        <i class="bi bi-person-badge-fill me-2"></i>Имя и фамилия
                                    </label>
                                    <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                        <input id="name" type="text"
                                               class="form-control @error('name') is-invalid @enderror"
                                               name="name" value="{{ old('name') }}"
                                               required autocomplete="name" autofocus
                                               placeholder="Иван Иванов">
                                        @error('name')
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">Как к вам обращаться</small>
                                </div>

                                <!-- Email -->
                                <div class="col-md-6 mb-4">
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
                                               required autocomplete="email"
                                               placeholder="example@email.com">
                                        @error('email')
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">На этот адрес придут уведомления</small>
                                </div>

                                <!-- Телефон -->
                                <div class="col-md-6 mb-4">
                                    <label for="phone" class="form-label fw-bold">
                                        <i class="bi bi-phone-fill me-2"></i>Телефон
                                    </label>
                                    <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-telephone"></i>
                                    </span>
                                        <input id="phone" type="tel"
                                               class="form-control @error('phone') is-invalid @enderror"
                                               name="phone" value="{{ old('phone') }}"
                                               required autocomplete="tel"
                                               placeholder="+7 (999) 123-45-67">
                                        @error('phone')
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">Для связи по бронированию</small>
                                </div>

                                <!-- Дата рождения -->
                                <div class="col-md-6 mb-4">
                                    <label for="birth_date" class="form-label fw-bold">
                                        <i class="bi bi-calendar-heart-fill me-2"></i>Дата рождения
                                    </label>
                                    <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-calendar"></i>
                                    </span>
                                        <input id="birth_date" type="date"
                                               class="form-control @error('birth_date') is-invalid @enderror"
                                               name="birth_date" value="{{ old('birth_date') }}"
                                               required
                                               max="{{ date('Y-m-d') }}">
                                        @error('birth_date')
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">Необходимо для бронирования</small>
                                </div>

                                <!-- Пароль -->
                                <div class="col-md-6 mb-4">
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
                                               autocomplete="new-password"
                                               placeholder="Минимум 8 символов">
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePassword('password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @error('password')
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar" id="passwordStrength"
                                                 role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="form-text" id="passwordHint"></small>
                                    </div>
                                </div>

                                <!-- Подтверждение пароля -->
                                <div class="col-md-6 mb-4">
                                    <label for="password_confirmation" class="form-label fw-bold">
                                        <i class="bi bi-key-fill me-2"></i>Подтвердите пароль
                                    </label>
                                    <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                        <input id="password_confirmation" type="password"
                                               class="form-control"
                                               name="password_confirmation" required
                                               autocomplete="new-password"
                                               placeholder="Повторите пароль">
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePassword('password_confirmation')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted" id="passwordMatch"></small>
                                </div>
                            </div>

                            <!-- Условия соглашения -->
                            <div class="form-group mb-4">
                                <div class="form-check">
                                    <input class="form-check-input @error('terms') is-invalid @enderror"
                                           type="checkbox" name="terms" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        Я согласен с
                                        <a href="{{ route('pages.show', 'terms') }}" target="_blank"
                                           class="text-decoration-none">условиями использования</a>
                                        и
                                        <a href="{{ route('pages.show', 'privacy') }}" target="_blank"
                                           class="text-decoration-none">политикой конфиденциальности</a>
                                    </label>
                                    @error('terms')
                                    <div class="invalid-feedback d-block">
                                        <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Кнопки -->
                            <div class="d-grid gap-3 mb-4">
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                    <i class="bi bi-person-check-fill me-2"></i>
                                    Зарегистрироваться
                                </button>

                                <a href="{{ route('login') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Уже есть аккаунт? Войти
                                </a>
                            </div>

                            <!-- Социальная авторизация (опционально) -->
                            <div class="text-center mb-3">
                                <p class="text-muted mb-2">Или зарегистрируйтесь через</p>
                                <div class="d-flex justify-content-center gap-3">
                                    <a href="#" class="btn btn-outline-primary">
                                        <i class="bi bi-google"></i> Google
                                    </a>
                                    <a href="#" class="btn btn-outline-dark">
                                        <i class="bi bi-github"></i> GitHub
                                    </a>
                                    <a href="#" class="btn btn-outline-info">
                                        <i class="bi bi-facebook"></i> Facebook
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-footer text-center py-3 bg-light">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            Регистрируясь, вы соглашаетесь с нашей политикой
                        </small>
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
        .input-group-text {
            background-color: #f8f9fa;
        }
        .progress-bar {
            transition: width 0.5s ease;
        }
        .password-strength .progress {
            background-color: #e9ecef;
        }
        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
            transition: all 0.3s;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        }
    </style>
@endpush

@push('scripts')
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');

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
            const strengthBar = document.getElementById('passwordStrength');
            const strengthHint = document.getElementById('passwordHint');
            const matchHint = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');

            // Проверка сложности пароля
            password.addEventListener('input', function() {
                const pass = this.value;
                let strength = 0;
                let hint = '';
                let color = 'bg-danger';

                // Проверки
                if (pass.length >= 8) strength++;
                if (pass.match(/[a-z]/) && pass.match(/[A-Z]/)) strength++;
                if (pass.match(/\d/)) strength++;
                if (pass.match(/[^a-zA-Z\d]/)) strength++;

                // Определение сложности
                switch(strength) {
                    case 0:
                    case 1:
                        hint = 'Слабый пароль';
                        color = 'bg-danger';
                        break;
                    case 2:
                        hint = 'Средний пароль';
                        color = 'bg-warning';
                        break;
                    case 3:
                        hint = 'Хороший пароль';
                        color = 'bg-info';
                        break;
                    case 4:
                        hint = 'Отличный пароль!';
                        color = 'bg-success';
                        break;
                }

                // Обновление интерфейса
                strengthBar.style.width = (strength * 25) + '%';
                strengthBar.className = 'progress-bar ' + color;
                strengthHint.textContent = hint;
                strengthHint.className = 'form-text ' +
                    (color === 'bg-danger' ? 'text-danger' :
                        color === 'bg-warning' ? 'text-warning' :
                            color === 'bg-info' ? 'text-info' : 'text-success');

                // Проверка совпадения паролей
                checkPasswordMatch();
            });

            // Проверка совпадения паролей
            function checkPasswordMatch() {
                if (password.value && confirmPassword.value) {
                    if (password.value === confirmPassword.value) {
                        matchHint.textContent = 'Пароли совпадают';
                        matchHint.className = 'form-text text-success';
                        submitBtn.disabled = false;
                    } else {
                        matchHint.textContent = 'Пароли не совпадают';
                        matchHint.className = 'form-text text-danger';
                        submitBtn.disabled = true;
                    }
                } else {
                    matchHint.textContent = '';
                    submitBtn.disabled = false;
                }
            }

            confirmPassword.addEventListener('input', checkPasswordMatch);

            // Валидация даты рождения
            const birthDate = document.getElementById('birth_date');
            if (birthDate) {
                const today = new Date().toISOString().split('T')[0];
                birthDate.max = today;

                // Установить минимальный возраст 18 лет
                const minDate = new Date();
                minDate.setFullYear(minDate.getFullYear() - 120);
                birthDate.min = minDate.toISOString().split('T')[0];
            }

            // Форматирование телефона
            const phone = document.getElementById('phone');
            if (phone) {
                phone.addEventListener('input', function(e) {
                    let value = this.value.replace(/\D/g, '');

                    if (value.startsWith('7') || value.startsWith('8')) {
                        value = value.substring(1);
                    }

                    if (value.length > 0) {
                        value = '+7 (' + value;
                    }
                    if (value.length > 7) {
                        value = value.substring(0, 7) + ') ' + value.substring(7);
                    }
                    if (value.length > 12) {
                        value = value.substring(0, 12) + '-' + value.substring(12);
                    }
                    if (value.length > 15) {
                        value = value.substring(0, 15) + '-' + value.substring(15);
                    }

                    this.value = value.substring(0, 18);
                });
            }

            // Автофокус на имя
            const nameField = document.getElementById('name');
            if (nameField) {
                nameField.focus();
            }
        });
    </script>
@endpush
