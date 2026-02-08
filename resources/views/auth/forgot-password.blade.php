@extends('layouts.guest')

@section('title', 'Восстановление пароля')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-warning text-white text-center py-4">
                        <h3 class="mb-0">
                            <i class="bi bi-key-fill me-2"></i>
                            Восстановление пароля
                        </h3>
                        <p class="mb-0 mt-2 small">Введите email для восстановления доступа</p>
                    </div>

                    <div class="card-body p-5">
                        <!-- Сессионные сообщения -->
                        @if (session('status'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                {{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.email') }}" id="forgotPasswordForm">
                            @csrf

                            <!-- Описание -->
                            <div class="text-center mb-4">
                                <div class="mb-3">
                                    <i class="bi bi-question-circle-fill text-warning fs-1"></i>
                                </div>
                                <p class="text-muted">
                                    Введите email адрес, указанный при регистрации.
                                    Мы отправим вам ссылку для сброса пароля.
                                </p>
                            </div>

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
                                <small class="form-text text-muted">
                                    Проверьте папку "Спам", если не получили письмо
                                </small>
                            </div>

                            <!-- Кнопка отправки -->
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-warning btn-lg" id="submitBtn">
                                    <i class="bi bi-send-fill me-2"></i>
                                    Отправить ссылку
                                </button>
                            </div>

                            <!-- Прогресс бар (опционально) -->
                            <div class="progress mb-3 d-none" id="progressBar" style="height: 5px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                     role="progressbar" style="width: 100%"></div>
                            </div>

                            <!-- Дополнительные ссылки -->
                            <div class="text-center">
                                <p class="mb-2">Вспомнили пароль?</p>
                                <a href="{{ route('login') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Вернуться ко входу
                                </a>
                            </div>

                            <!-- Информация о времени -->
                            <div class="alert alert-info mt-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                                    <div>
                                        <h6 class="alert-heading mb-1">Важно!</h6>
                                        <p class="mb-0 small">
                                            Ссылка для сброса пароля действительна в течение
                                            <strong>60 минут</strong>. Если не получили письмо,
                                            проверьте папку "Спам" или попробуйте снова через 5 минут.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-footer text-center py-3 bg-light">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            Ссылка для сброса пароля будет отправлена только на зарегистрированный email
                        </small>
                    </div>
                </div>

                <!-- Контактная информация -->
                <div class="card mt-4 border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-info mb-3">
                            <i class="bi bi-headset me-2"></i>Нужна помощь?
                        </h6>
                        <p class="card-text small mb-0">
                            Если у вас возникли проблемы с восстановлением доступа,
                            свяжитесь с нашей службой поддержки:
                        </p>
                        <div class="mt-2">
                            <a href="mailto:support@{{ config('app.domain') }}" class="btn btn-sm btn-outline-info me-2">
                                <i class="bi bi-envelope"></i> support@{{ config('app.domain') }}
                            </a>
                            <a href="{{ route('contact.index') }}" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-chat-dots"></i> Форма обратной связи
                            </a>
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
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            border: none;
            color: #212529;
            font-weight: 600;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800 0%, #c69500 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        .progress-bar {
            background-color: #ffc107;
        }
        .alert-info {
            background-color: rgba(13, 202, 240, 0.1);
            border-color: #0dcaf0;
            border-radius: 10px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const submitBtn = document.getElementById('submitBtn');
            const progressBar = document.getElementById('progressBar');
            const emailField = document.getElementById('email');

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Валидация email
                    const email = emailField.value.trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                    if (!email) {
                        showAlert('Пожалуйста, введите email адрес', 'danger');
                        emailField.focus();
                        return;
                    }

                    if (!emailRegex.test(email)) {
                        showAlert('Пожалуйста, введите корректный email адрес', 'danger');
                        emailField.focus();
                        return;
                    }

                    // Показать прогресс бар
                    if (progressBar) {
                        progressBar.classList.remove('d-none');
                    }

                    // Блокировать кнопку
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Отправка...';
                    }

                    // Отправить форму
                    this.submit();
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

                const form = document.getElementById('forgotPasswordForm');
                if (form) {
                    form.insertBefore(alertDiv, form.firstChild);

                    // Автоматически скрыть через 5 секунд
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 5000);
                }
            }

            // Автофокус на поле email
            if (emailField) {
                emailField.focus();
            }

            // Показать анимацию отправки при наличии сообщения об успехе
            @if(session('status'))
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Отправлено!';
                submitBtn.className = 'btn btn-success btn-lg';
            }
            @endif
        });
    </script>
@endpush
