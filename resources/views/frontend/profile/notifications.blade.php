@extends('layouts.app')

@section('title', 'Мои уведомления')

@section('content')
    <div class="container py-5">
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item"><a href="{{ route('profile.index') }}">Личный кабинет</a></li>
                <li class="breadcrumb-item active" aria-current="page">Уведомления</li>
            </ol>
        </nav>

        <div class="row">
            <!-- Сайдбар -->
            <div class="col-lg-3 mb-4">
                @include('frontend.profile.partials.sidebar')
            </div>

            <!-- Основной контент -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 fw-bold mb-0">
                                    <i class="bi bi-bell text-primary me-2"></i>
                                    Мои уведомления
                                </h1>
                                <p class="text-muted mb-0 mt-2 small">
                                    {{ $unreadCount > 0 ? "У вас $unreadCount новых уведомлений" : 'Нет новых уведомлений' }}
                                </p>
                            </div>
                            <div class="btn-group">
                                @if($notifications->count() > 0)
                                    <button type="button" class="btn btn-outline-primary" id="markAllRead">
                                        <i class="bi bi-check2-all me-2"></i>Прочитать все
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" id="deleteAllRead">
                                        <i class="bi bi-trash me-2"></i>Удалить прочитанные
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- Фильтры -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('profile.notifications') }}"
                                       class="btn btn-outline-secondary {{ !request('filter') ? 'active' : '' }}">
                                        Все ({{ $totalCount }})
                                    </a>
                                    <a href="{{ route('profile.notifications', ['filter' => 'unread']) }}"
                                       class="btn btn-outline-primary {{ request('filter') == 'unread' ? 'active' : '' }}">
                                        Непрочитанные ({{ $unreadCount }})
                                    </a>
                                    <a href="{{ route('profile.notifications', ['filter' => 'read']) }}"
                                       class="btn btn-outline-success {{ request('filter') == 'read' ? 'active' : '' }}">
                                        Прочитанные ({{ $readCount }})
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary w-100 dropdown-toggle" type="button"
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-filter me-2"></i>
                                        {{ request('type') ? ucfirst(request('type')) : 'Все типы' }}
                                    </button>
                                    <ul class="dropdown-menu w-100">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('profile.notifications', array_merge(request()->except('type'), ['type' => ''])) }}">
                                                Все типы
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('profile.notifications', array_merge(request()->except('type'), ['type' => 'system'])) }}">
                                                <i class="bi bi-info-circle text-info me-2"></i>Системные
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('profile.notifications', array_merge(request()->except('type'), ['type' => 'booking'])) }}">
                                                <i class="bi bi-calendar-check text-primary me-2"></i>Бронирования
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('profile.notifications', array_merge(request()->except('type'), ['type' => 'payment'])) }}">
                                                <i class="bi bi-credit-card text-success me-2"></i>Платежи
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('profile.notifications', array_merge(request()->except('type'), ['type' => 'promo'])) }}">
                                                <i class="bi bi-percent text-warning me-2"></i>Акции
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('profile.notifications', array_merge(request()->except('type'), ['type' => 'review'])) }}">
                                                <i class="bi bi-star text-secondary me-2"></i>Отзывы
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Действия с выбранными -->
                        <div class="d-none" id="selectionActions">
                            <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <i class="bi bi-info-circle me-2"></i>
                                    <span id="selectedCount">0</span> уведомлений выбрано
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="markSelectedRead">
                                        <i class="bi bi-check2 me-1"></i>Прочитать
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="deleteSelected">
                                        <i class="bi bi-trash me-1"></i>Удалить
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSelection">
                                        <i class="bi bi-x-circle me-1"></i>Снять выделение
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Список уведомлений -->
                        @if($notifications->count() > 0)
                            <div class="list-group">
                                @foreach($notifications as $notification)
                                    <div class="list-group-item list-group-item-action border-0 rounded-3 mb-2
                                            {{ $notification->read_at ? 'bg-light' : 'bg-white border-start border-3 border-primary' }}"
                                         data-notification-id="{{ $notification->id }}"
                                         onclick="toggleNotificationSelection(event, {{ $notification->id }})">

                                        <div class="d-flex align-items-start">
                                            <!-- Чекбокс для выбора -->
                                            <div class="flex-shrink-0 mt-1 me-3">
                                                <input type="checkbox" class="form-check-input notification-checkbox"
                                                       data-notification-id="{{ $notification->id }}"
                                                       onclick="event.stopPropagation()">
                                            </div>

                                            <!-- Иконка типа уведомления -->
                                            <div class="flex-shrink-0 me-3">
                                                @if($notification->type == 'system')
                                                    <div class="bg-info text-white rounded-circle p-2">
                                                        <i class="bi bi-info-circle fs-5"></i>
                                                    </div>
                                                @elseif($notification->type == 'booking')
                                                    <div class="bg-primary text-white rounded-circle p-2">
                                                        <i class="bi bi-calendar-check fs-5"></i>
                                                    </div>
                                                @elseif($notification->type == 'payment')
                                                    <div class="bg-success text-white rounded-circle p-2">
                                                        <i class="bi bi-credit-card fs-5"></i>
                                                    </div>
                                                @elseif($notification->type == 'promo')
                                                    <div class="bg-warning text-white rounded-circle p-2">
                                                        <i class="bi bi-percent fs-5"></i>
                                                    </div>
                                                @elseif($notification->type == 'review')
                                                    <div class="bg-secondary text-white rounded-circle p-2">
                                                        <i class="bi bi-star fs-5"></i>
                                                    </div>
                                                @else
                                                    <div class="bg-dark text-white rounded-circle p-2">
                                                        <i class="bi bi-bell fs-5"></i>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Контент уведомления -->
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="fw-bold mb-0">
                                                        {{ $notification->title ?? $notification->type_title }}
                                                        @if(!$notification->read_at)
                                                            <span class="badge bg-danger ms-2">Новое</span>
                                                        @endif
                                                    </h6>
                                                    <div class="dropdown">
                                                        <button class="btn btn-link text-muted p-0" type="button"
                                                                data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            @if(!$notification->read_at)
                                                                <li>
                                                                    <button class="dropdown-item"
                                                                            onclick="markAsRead({{ $notification->id }}, event)">
                                                                        <i class="bi bi-check2 me-2"></i>Пометить как прочитанное
                                                                    </button>
                                                                </li>
                                                            @endif
                                                            <li>
                                                                <button class="dropdown-item text-danger"
                                                                        onclick="deleteNotification({{ $notification->id }}, event)">
                                                                    <i class="bi bi-trash me-2"></i>Удалить
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>

                                                <p class="mb-2">{{ $notification->message }}</p>

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock me-1"></i>
                                                        {{ $notification->created_at->diffForHumans() }}
                                                        @if($notification->scheduled_at)
                                                            • Запланировано на {{ $notification->scheduled_at->format('d.m.Y H:i') }}
                                                        @endif
                                                    </small>

                                                    <!-- Действия -->
                                                    <div class="btn-group btn-group-sm">
                                                        @if($notification->action_url)
                                                            <a href="{{ $notification->action_url }}"
                                                               class="btn btn-outline-primary btn-sm"
                                                               onclick="event.stopPropagation()">
                                                                {{ $notification->action_text ?? 'Подробнее' }}
                                                            </a>
                                                        @endif

                                                        @if($notification->data['booking_id'] ?? false)
                                                            <a href="{{ route('profile.bookings.show', $notification->data['booking_id']) }}"
                                                               class="btn btn-outline-info btn-sm"
                                                               onclick="event.stopPropagation()">
                                                                <i class="bi bi-calendar-check me-1"></i>Бронирование
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Пагинация -->
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="small text-muted">
                                    Показано {{ $notifications->firstItem() }}-{{ $notifications->lastItem() }} из {{ $notifications->total() }}
                                </div>
                                <nav aria-label="Навигация по страницам">
                                    {{ $notifications->withQueryString()->links() }}
                                </nav>
                            </div>
                        @else
                            <!-- Сообщение об отсутствии уведомлений -->
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="bi bi-bell-slash text-muted" style="font-size: 4rem;"></i>
                                </div>
                                <h4 class="fw-bold mb-3">Уведомлений нет</h4>
                                <p class="text-muted mb-4">
                                    @if(request('filter') == 'unread')
                                        У вас нет непрочитанных уведомлений
                                    @elseif(request('type'))
                                        Нет уведомлений этого типа
                                    @else
                                        Здесь будут отображаться все ваши уведомления
                                    @endif
                                </p>
                                <a href="{{ route('profile.notifications') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Показать все уведомления
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Настройки уведомлений -->
                    <div class="card-footer bg-light border-0 p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1">
                                    <i class="bi bi-gear text-primary me-2"></i>
                                    Настройки уведомлений
                                </h6>
                                <p class="text-muted small mb-0">Управляйте получением уведомлений</p>
                            </div>
                            <a href="{{ route('profile.settings') }}" class="btn btn-outline-primary">
                                <i class="bi bi-sliders me-2"></i>Настроить
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Статистика уведомлений -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-envelope text-primary fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ $totalCount }}</h3>
                                <p class="text-muted mb-0">Всего уведомлений</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-envelope-open text-success fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ $readCount }}</h3>
                                <p class="text-muted mb-0">Прочитано</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-envelope-plus text-warning fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ $unreadCount }}</h3>
                                <p class="text-muted mb-0">Непрочитано</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-calendar-week text-info fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ $todayCount }}</h3>
                                <p class="text-muted mb-0">Сегодня</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Подтверждение удаления
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить выбранные уведомления?</p>
                    <p class="text-muted small">Это действие нельзя отменить.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteSelected">
                        <i class="bi bi-trash me-2"></i>Удалить
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .list-group-item {
            transition: all 0.2s;
            cursor: pointer;
        }

        .list-group-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .list-group-item.bg-white {
            border-left: 3px solid #0d6efd !important;
        }

        .rounded-circle {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-checkbox {
            transform: scale(1.2);
        }

        .badge {
            font-size: 0.7em;
            padding: 0.3em 0.6em;
        }

        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .dropdown-menu {
            min-width: 200px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let selectedNotifications = new Set();
            const selectionActions = document.getElementById('selectionActions');
            const selectedCount = document.getElementById('selectedCount');

            // Функция переключения выбора уведомления
            window.toggleNotificationSelection = function(event, notificationId) {
                event.stopPropagation();

                const checkbox = document.querySelector(`.notification-checkbox[data-notification-id="${notificationId}"]`);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    updateSelection(notificationId, checkbox.checked);
                }
            };

            // Обновление выбора
            function updateSelection(notificationId, isSelected) {
                if (isSelected) {
                    selectedNotifications.add(notificationId);
                } else {
                    selectedNotifications.delete(notificationId);
                }

                updateSelectionUI();
            }

            // Обновление UI выбора
            function updateSelectionUI() {
                const count = selectedNotifications.size;

                if (count > 0) {
                    if (selectionActions) {
                        selectionActions.classList.remove('d-none');
                        selectedCount.textContent = count;
                    }
                } else {
                    if (selectionActions) {
                        selectionActions.classList.add('d-none');
                    }
                }

                // Обновить все чекбоксы
                document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                    const id = parseInt(checkbox.dataset.notificationId);
                    checkbox.checked = selectedNotifications.has(id);
                });
            }

            // Обработчик кликов по чекбоксам
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const notificationId = parseInt(this.dataset.notificationId);
                    updateSelection(notificationId, this.checked);
                });
            });

            // Пометить все как прочитанные
            document.getElementById('markAllRead')?.addEventListener('click', function() {
                if (confirm('Пометить все уведомления как прочитанные?')) {
                    fetch('{{ route("profile.notifications.mark-all-read") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            }
                        });
                }
            });

            // Удалить все прочитанные
            document.getElementById('deleteAllRead')?.addEventListener('click', function() {
                if (confirm('Удалить все прочитанные уведомления?')) {
                    fetch('{{ route("profile.notifications.delete-read") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            }
                        });
                }
            });

            // Пометить выбранные как прочитанные
            document.getElementById('markSelectedRead')?.addEventListener('click', function() {
                const ids = Array.from(selectedNotifications);
                if (ids.length === 0) return;

                fetch('{{ route("profile.notifications.mark-read") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ ids: ids })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            selectedNotifications.clear();
                            updateSelectionUI();
                            // Обновить UI уведомлений
                            ids.forEach(id => {
                                const item = document.querySelector(`[data-notification-id="${id}"]`);
                                if (item) {
                                    item.classList.remove('bg-white', 'border-start', 'border-primary');
                                    item.classList.add('bg-light');
                                    const badge = item.querySelector('.badge');
                                    if (badge) badge.remove();
                                }
                            });
                        }
                    });
            });

            // Удалить выбранные
            document.getElementById('deleteSelected')?.addEventListener('click', function() {
                const ids = Array.from(selectedNotifications);
                if (ids.length === 0) return;

                // Показать модальное окно подтверждения
                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
                modal.show();

                document.getElementById('confirmDeleteSelected').onclick = function() {
                    fetch('{{ route("profile.notifications.delete") }}', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ ids: ids })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Удалить элементы из DOM
                                ids.forEach(id => {
                                    const item = document.querySelector(`[data-notification-id="${id}"]`);
                                    if (item) item.remove();
                                });

                                selectedNotifications.clear();
                                updateSelectionUI();
                                modal.hide();

                                // Обновить счетчики
                                updateCounters();
                            }
                        });
                };
            });

            // Снять выделение
            document.getElementById('clearSelection')?.addEventListener('click', function() {
                selectedNotifications.clear();
                updateSelectionUI();
            });

            // Функция пометки как прочитанного
            window.markAsRead = function(notificationId, event) {
                event.stopPropagation();

                fetch(`/profile/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
                            if (item) {
                                item.classList.remove('bg-white', 'border-start', 'border-primary');
                                item.classList.add('bg-light');
                                const badge = item.querySelector('.badge');
                                if (badge) badge.remove();
                            }
                        }
                    });
            };

            // Функция удаления уведомления
            window.deleteNotification = function(notificationId, event) {
                event.stopPropagation();

                if (confirm('Удалить это уведомление?')) {
                    fetch(`/profile/notifications/${notificationId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
                                if (item) item.remove();
                                updateCounters();
                            }
                        });
                }
            };

            // Обновление счетчиков
            function updateCounters() {
                // Обновить счетчики на странице
                const unreadBadge = document.querySelector('.badge[data-notification-count]');
                if (unreadBadge) {
                    const current = parseInt(unreadBadge.textContent);
                    const selectedCount = selectedNotifications.size;
                    unreadBadge.textContent = Math.max(0, current - selectedCount);
                }
            }

            // Автоматическое обновление уведомлений
            function checkNewNotifications() {
                fetch('{{ route("profile.notifications.check-new") }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_new && !document.hidden) {
                            // Показать уведомление в браузере
                            if (Notification.permission === "granted") {
                                new Notification("Новые уведомления", {
                                    body: "У вас есть новые уведомления",
                                    icon: "/img/notification-icon.png"
                                });
                            }

                            // Обновить страницу
                            window.location.reload();
                        }
                    });
            }

            // Запросить разрешение на уведомления
            if (Notification.permission === "default") {
                Notification.requestPermission();
            }

            // Проверять новые уведомления каждые 30 секунд
            setInterval(checkNewNotifications, 30000);

            // Уведомления при возвращении на вкладку
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    checkNewNotifications();
                }
            });
        });
    </script>
@endpush
