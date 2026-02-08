@extends('layouts.app')

@section('title', 'Страница не найдена (404)')

@section('content')
    <div class="container py-5 my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <!-- Анимация или иконка -->
                <div class="error-icon mb-4">
                    <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 8rem;"></i>
                </div>

                <!-- Заголовок -->
                <h1 class="display-1 fw-bold text-primary mb-3">404</h1>
                <h2 class="h3 fw-bold mb-4">Страница не найдена</h2>

                <!-- Сообщение -->
                <div class="alert alert-warning border-warning mb-5">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                        <div class="text-start">
                            <p class="mb-1 fw-bold">Что могло произойти:</p>
                            <ul class="mb-0 ps-3">
                                <li>Страница была удалена или перемещена</li>
                                <li>Вы ввели неправильный адрес</li>
                                <li>Страница временно недоступна</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Поиск по сайту -->
                <div class="card shadow-sm border-0 mb-5">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-search me-2"></i>Попробуйте найти нужную информацию
                        </h5>
                        <form action="{{ route('search.results') }}" method="GET" class="mb-0">
                            <div class="input-group">
                                <input type="text" class="form-control form-control-lg"
                                       name="q" placeholder="Введите ключевые слова..."
                                       required>
                                <button class="btn btn-primary btn-lg" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Быстрые ссылки -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-house-door text-primary fs-1 mb-3"></i>
                                <h5 class="card-title fw-bold">На главную</h5>
                                <p class="card-text text-muted mb-3">
                                    Вернитесь на главную страницу сайта
                                </p>
                                <a href="{{ route('home') }}" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-house me-2"></i>Перейти
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-telephone text-success fs-1 mb-3"></i>
                                <h5 class="card-title fw-bold">Связаться с нами</h5>
                                <p class="card-text text-muted mb-3">
                                    Нужна помощь? Свяжитесь с поддержкой
                                </p>
                                <a href="{{ route('contact.index') }}" class="btn btn-outline-success w-100">
                                    <i class="bi bi-chat-dots me-2"></i>Контакты
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Дополнительная помощь -->
                <div class="mt-5 pt-4 border-top">
                    <p class="text-muted mb-3">
                        Если вы считаете, что это ошибка, пожалуйста, сообщите нам:
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <button class="btn btn-outline-warning" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Обновить страницу
                        </button>
                        <a href="mailto:support@{{ config('app.domain') }}?subject=Ошибка 404&body=Страница: {{ url()->current() }}"
                           class="btn btn-outline-info">
                            <i class="bi bi-bug me-2"></i>Сообщить об ошибке
                        </a>
                        <button class="btn btn-outline-secondary" onclick="window.history.back()">
                            <i class="bi bi-arrow-left me-2"></i>Вернуться назад
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Детали ошибки (только для админов) -->
    @auth
        @if(auth()->user()->isAdmin())
            <div class="container mt-5">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-bug-fill me-2"></i>Техническая информация (только для администраторов)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tbody>
                            <tr>
                                <th width="150">URL:</th>
                                <td>{{ url()->current() }}</td>
                            </tr>
                            <tr>
                                <th>Время:</th>
                                <td>{{ now()->format('d.m.Y H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th>Пользователь:</th>
                                <td>{{ auth()->user()->email }} (ID: {{ auth()->user()->id }})</td>
                            </tr>
                            <tr>
                                <th>IP адрес:</th>
                                <td>{{ request()->ip() }}</td>
                            </tr>
                            <tr>
                                <th>User Agent:</th>
                                <td><small>{{ request()->userAgent() }}</small></td>
                            </tr>
                            <tr>
                                <th>Реферер:</th>
                                <td>{{ request()->header('referer') ?? 'Не указан' }}</td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="text-end">
                            <a href="{{ route('admin.audit-logs.create', ['type' => '404', 'url' => url()->current()]) }}"
                               class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-journal-plus me-1"></i>Записать в журнал
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endauth
@endsection

@push('styles')
    <style>
        .error-icon {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }

        .card {
            border-radius: 15px;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .btn-outline-warning:hover {
            background-color: #ffc107;
            color: #212529;
        }

        @media (max-width: 768px) {
            .display-1 {
                font-size: 4rem;
            }

            .error-icon i {
                font-size: 6rem;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Автофокус на поле поиска
            const searchInput = document.querySelector('input[name="q"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Отслеживание ошибки в Google Analytics (если подключен)
            @if(config('services.google.analytics'))
            if (typeof gtag === 'function') {
                gtag('event', 'error', {
                    'event_category': '404',
                    'event_label': '{{ url()->current() }}',
                    'value': 1
                });
            }
            @endif

            // Логирование ошибки в консоль
            console.error('404 Error: Page not found - {{ url()->current() }}');

            // Таймер обратного отсчета для автоматического редиректа
            let countdown = 10;
            const countdownElement = document.createElement('div');
            countdownElement.className = 'alert alert-info mt-3';
            countdownElement.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-clock-history me-3 fs-4"></i>
                <div>
                    <p class="mb-0">Автоматический возврат на главную страницу через <span id="countdownTimer">${countdown}</span> секунд</p>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="countdownProgress" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        `;

            const mainContent = document.querySelector('.container.py-5');
            if (mainContent) {
                mainContent.appendChild(countdownElement);

                const timerInterval = setInterval(() => {
                    countdown--;
                    const timerElement = document.getElementById('countdownTimer');
                    const progressElement = document.getElementById('countdownProgress');

                    if (timerElement) {
                        timerElement.textContent = countdown;
                    }

                    if (progressElement) {
                        const progressWidth = (countdown / 10) * 100;
                        progressElement.style.width = `${progressWidth}%`;
                    }

                    if (countdown <= 0) {
                        clearInterval(timerInterval);
                        window.location.href = '{{ route("home") }}';
                    }
                }, 1000);

                // Остановить таймер при закрытии уведомления
                countdownElement.querySelector('.btn-close').addEventListener('click', function() {
                    clearInterval(timerInterval);
                    countdownElement.remove();
                });
            }
        });
    </script>
@endpush
