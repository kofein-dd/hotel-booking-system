@extends('layouts.app')

@section('title', 'Ошибка сервера (500)')

@section('content')
    <div class="container py-5 my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <!-- Анимация или иконка -->
                <div class="error-icon mb-4">
                    <div class="position-relative d-inline-block">
                        <i class="bi bi-server text-danger" style="font-size: 8rem;"></i>
                        <i class="bi bi-exclamation-circle-fill text-warning position-absolute top-0 start-100 translate-middle"
                           style="font-size: 3rem;"></i>
                    </div>
                </div>

                <!-- Заголовок -->
                <h1 class="display-1 fw-bold text-danger mb-3">500</h1>
                <h2 class="h3 fw-bold mb-4">Внутренняя ошибка сервера</h2>

                <!-- Сообщение -->
                <div class="alert alert-danger border-danger mb-5">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-octagon-fill me-3 fs-4"></i>
                        <div class="text-start">
                            <p class="mb-2 fw-bold">На сервере произошла непредвиденная ошибка.</p>
                            <p class="mb-0">Наши технические специалисты уже работают над решением проблемы.</p>
                        </div>
                    </div>
                </div>

                <!-- Что делать -->
                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="bi bi-arrow-clockwise text-primary fs-1"></i>
                                </div>
                                <h5 class="card-title fw-bold">Обновите страницу</h5>
                                <p class="card-text text-muted mb-3">
                                    Попробуйте обновить страницу через несколько минут
                                </p>
                                <button onclick="window.location.reload()" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Обновить сейчас
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="bi bi-house text-success fs-1"></i>
                                </div>
                                <h5 class="card-title fw-bold">На главную</h5>
                                <p class="card-text text-muted mb-3">
                                    Вернитесь на главную страницу сайта
                                </p>
                                <a href="{{ route('home') }}" class="btn btn-success w-100">
                                    <i class="bi bi-house me-2"></i>На главную
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Сообщить об ошибке -->
                <div class="card border-warning mb-5">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-bug-fill me-2"></i>Сообщить об ошибке
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">
                            Если ошибка повторяется, пожалуйста, сообщите нам об этом:
                        </p>
                        <div class="d-flex flex-wrap gap-3 justify-content-center">
                            <a href="mailto:tech@{{ config('app.domain') }}?subject=Ошибка 500&body=URL: {{ url()->current() }}%0AВремя: {{ now()->format('d.m.Y H:i:s') }}%0AОписание: "
                               class="btn btn-outline-warning">
                                <i class="bi bi-envelope me-2"></i>Написать на email
                            </a>
                            <a href="{{ route('contact.index') }}" class="btn btn-outline-info">
                                <i class="bi bi-chat-dots me-2"></i>Форма обратной связи
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Статус системы -->
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                        <div class="text-start">
                            <p class="mb-1 fw-bold">Текущий статус системы:</p>
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-danger me-2">Сервер</span>
                                <span class="text-muted">Обнаружена ошибка</span>
                            </div>
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-warning me-2">База данных</span>
                                <span class="text-muted">Проверяется</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2">Сайт</span>
                                <span class="text-muted">Частично доступен</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Техническая информация (только для админов) -->
    @auth
        @if(auth()->user()->isAdmin())
            <div class="container mt-5">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-terminal-fill me-2"></i>Техническая информация (только для администраторов)
                        </h5>
                        <button class="btn btn-sm btn-outline-light" type="button"
                                data-bs-toggle="collapse" data-bs-target="#techDetails">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse show" id="techDetails">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2">Информация об ошибке</h6>
                                    <table class="table table-sm table-borderless">
                                        <tbody>
                                        <tr>
                                            <th width="140">Код ошибки:</th>
                                            <td><span class="badge bg-danger">500</span></td>
                                        </tr>
                                        <tr>
                                            <th>Время:</th>
                                            <td>{{ now()->format('d.m.Y H:i:s') }}</td>
                                        </tr>
                                        <tr>
                                            <th>URL:</th>
                                            <td><code>{{ url()->current() }}</code></td>
                                        </tr>
                                        <tr>
                                            <th>Метод:</th>
                                            <td>{{ request()->method() }}</td>
                                        </tr>
                                        <tr>
                                            <th>Пользователь:</th>
                                            <td>{{ auth()->user()->email ?? 'Не авторизован' }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2">Системная информация</h6>
                                    <table class="table table-sm table-borderless">
                                        <tbody>
                                        <tr>
                                            <th width="140">Laravel:</th>
                                            <td>{{ app()->version() }}</td>
                                        </tr>
                                        <tr>
                                            <th>PHP:</th>
                                            <td>{{ phpversion() }}</td>
                                        </tr>
                                        <tr>
                                            <th>Сервер:</th>
                                            <td>{{ request()->server('SERVER_SOFTWARE') }}</td>
                                        </tr>
                                        <tr>
                                            <th>IP:</th>
                                            <td>{{ request()->ip() }}</td>
                                        </tr>
                                        <tr>
                                            <th>Сессия:</th>
                                            <td>{{ session()->getId() }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Действия -->
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="mb-3">Быстрые действия:</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-speedometer2 me-1"></i>Панель управления
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning" onclick="clearCache()">
                                        <i class="bi bi-trash me-1"></i>Очистить кэш
                                    </button>
                                    <a href="{{ route('admin.backups.index') }}" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-database-check me-1"></i>Проверить БД
                                    </a>
                                    <a href="{{ route('logs.index') }}" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-journal-text me-1"></i>Посмотреть логи
                                    </a>
                                </div>
                            </div>

                            <!-- Логи ошибок (последние 5) -->
                            @if(file_exists(storage_path('logs/laravel.log')))
                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="mb-3">Последние ошибки в логах:</h6>
                                    <div class="log-preview bg-dark text-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                                    <pre class="mb-0 small"><code>
@php
    try {
        $logContent = file_get_contents(storage_path('logs/laravel.log'));
        $lines = array_slice(explode("\n", $logContent), -20);
        echo htmlspecialchars(implode("\n", $lines));
    } catch (Exception $e) {
        echo "Не удалось прочитать файл логов";
    }
@endphp
                                    </code></pre>
                                    </div>
                                </div>
                            @endif
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
            animation: shake 1s infinite;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }

        .log-preview {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .log-preview code {
            color: #20c997;
        }

        .card {
            border-radius: 15px;
            overflow: hidden;
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
        function clearCache() {
            if (confirm('Вы уверены, что хотите очистить кэш системы?')) {
                fetch('{{ route("admin.clear-cache") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Кэш успешно очищен!');
                            window.location.reload();
                        } else {
                            alert('Ошибка при очистке кэша: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Ошибка сети при очистке кэша');
                    });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Автоматическое обновление через 30 секунд
            setTimeout(() => {
                const refreshBtn = document.querySelector('[onclick="window.location.reload()"]');
                if (refreshBtn) {
                    refreshBtn.click();
                }
            }, 30000);

            // Отслеживание ошибки в Google Analytics
            @if(config('services.google.analytics'))
            if (typeof gtag === 'function') {
                gtag('event', 'error', {
                    'event_category': '500',
                    'event_label': '{{ url()->current() }}',
                    'value': 1
                });
            }
            @endif

            // Логирование ошибки в консоль
            console.error('500 Error: Internal Server Error - {{ url()->current() }}');

            // Мониторинг статуса системы
            function checkSystemStatus() {
                fetch('{{ route("system.status") }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'healthy') {
                            // Если система восстановилась, предложить обновить страницу
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success alert-dismissible fade show';
                            alertDiv.innerHTML = `
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                                <div>
                                    <h6 class="alert-heading mb-1">Система восстановлена!</h6>
                                    <p class="mb-0">Все службы работают нормально. Страница будет обновлена автоматически через 5 секунд.</p>
                                </div>
                            </div>
                        `;

                            document.querySelector('.container.py-5').prepend(alertDiv);

                            setTimeout(() => {
                                window.location.reload();
                            }, 5000);
                        }
                    })
                    .catch(error => {
                        console.log('System check failed:', error);
                    });
            }

            // Проверять статус каждые 30 секунд
            setInterval(checkSystemStatus, 30000);
        });
    </script>
@endpush
