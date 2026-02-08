@extends('layouts.app')

@section('title', 'Личный кабинет - ' . Auth::user()->name)

@section('content')
    <div class="container py-5">
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item active" aria-current="page">Личный кабинет</li>
            </ol>
        </nav>

        <div class="row">
            <!-- Сайдбар профиля -->
            <div class="col-lg-3 mb-4">
                @include('frontend.profile.partials.sidebar')
            </div>

            <!-- Основной контент -->
            <div class="col-lg-9">
                <!-- Приветствие -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar avatar-xl bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                                    @if(Auth::user()->avatar_url)
                                        <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}"
                                             class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                                    @else
                                        <span class="fs-2">{{ substr(Auth::user()->name, 0, 1) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-4">
                                <h1 class="h3 fw-bold mb-1">Добро пожаловать, {{ Auth::user()->name }}!</h1>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-envelope me-1"></i> {{ Auth::user()->email }}
                                    @if(Auth::user()->email_verified_at)
                                        <span class="badge bg-success ms-2">
                                        <i class="bi bi-check-circle me-1"></i>Подтвержден
                                    </span>
                                    @else
                                        <span class="badge bg-warning ms-2">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Не подтвержден
                                    </span>
                                    @endif
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-calendar me-1"></i> В системе с {{ Auth::user()->created_at->format('d.m.Y') }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil-square me-2"></i>Редактировать
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="text-primary mb-3">
                                    <i class="bi bi-calendar-check fs-1"></i>
                                </div>
                                <h3 class="fw-bold mb-2">{{ $totalBookings }}</h3>
                                <p class="text-muted mb-0">Всего бронирований</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="text-warning mb-3">
                                    <i class="bi bi-clock-history fs-1"></i>
                                </div>
                                <h3 class="fw-bold mb-2">{{ $activeBookings }}</h3>
                                <p class="text-muted mb-0">Активные брони</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="text-success mb-3">
                                    <i class="bi bi-star fs-1"></i>
                                </div>
                                <h3 class="fw-bold mb-2">{{ $totalReviews }}</h3>
                                <p class="text-muted mb-0">Мои отзывы</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="text-info mb-3">
                                    <i class="bi bi-bell fs-1"></i>
                                </div>
                                <h3 class="fw-bold mb-2">{{ $unreadNotifications }}</h3>
                                <p class="text-muted mb-0">Новые уведомления</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Активные бронирования -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-calendar-check text-primary me-2"></i>
                            Ближайшие бронирования
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @if($upcomingBookings->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Номер</th>
                                        <th>Даты</th>
                                        <th>Гости</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($upcomingBookings as $booking)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @if($booking->room->photos && count($booking->room->photos) > 0)
                                                        <img src="{{ asset($booking->room->photos[0]) }}"
                                                             alt="{{ $booking->room->name }}"
                                                             class="rounded me-3" style="width: 60px; height: 40px; object-fit: cover;">
                                                    @endif
                                                    <div>
                                                        <strong>{{ $booking->room->name }}</strong>
                                                        <div class="small text-muted">{{ $booking->room->type }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $booking->check_in->format('d.m.Y') }}</div>
                                                <div class="small text-muted">{{ $booking->nights_count }} ночей</div>
                                            </td>
                                            <td>
                                                <div>{{ $booking->guests_count }} чел.</div>
                                            </td>
                                            <td>
                                                <strong>{{ number_format($booking->total_price, 0, '', ' ') }} ₽</strong>
                                            </td>
                                            <td>
                                                @if($booking->status == 'confirmed')
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Подтверждено
                                                    </span>
                                                @elseif($booking->status == 'pending')
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-clock me-1"></i>Ожидает
                                                    </span>
                                                @elseif($booking->status == 'cancelled')
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Отменено
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('profile.bookings.show', $booking->id) }}"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 mb-2">Нет активных бронирований</h5>
                                <p class="text-muted mb-4">У вас пока нет предстоящих поездок</p>
                                <a href="{{ route('rooms.index') }}" class="btn btn-primary">
                                    <i class="bi bi-search me-2"></i>Найти номер
                                </a>
                            </div>
                        @endif
                    </div>
                    @if($upcomingBookings->count() > 0)
                        <div class="card-footer bg-white border-0 py-3">
                            <div class="text-end">
                                <a href="{{ route('profile.bookings') }}" class="btn btn-link">
                                    Все бронирования <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Быстрые действия -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">
                                    <i class="bi bi-bell text-warning me-2"></i>
                                    Уведомления
                                </h5>
                                @if($recentNotifications->count() > 0)
                                    <div class="list-group list-group-flush">
                                        @foreach($recentNotifications as $notification)
                                            <div class="list-group-item border-0 px-0 py-2">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0 mt-1">
                                                        @if($notification->type == 'booking')
                                                            <i class="bi bi-calendar-check text-primary"></i>
                                                        @elseif($notification->type == 'payment')
                                                            <i class="bi bi-credit-card text-success"></i>
                                                        @elseif($notification->type == 'system')
                                                            <i class="bi bi-info-circle text-info"></i>
                                                        @else
                                                            <i class="bi bi-bell text-warning"></i>
                                                        @endif
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="mb-1">{{ Str::limit($notification->message, 80) }}</p>
                                                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                                    </div>
                                                    @if(!$notification->read_at)
                                                        <span class="badge bg-danger rounded-pill">Новое</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">Нет новых уведомлений</p>
                                @endif
                                <div class="mt-3">
                                    <a href="{{ route('profile.notifications') }}" class="btn btn-outline-warning w-100">
                                        <i class="bi bi-bell me-2"></i>Все уведомления
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">
                                    <i class="bi bi-star text-success me-2"></i>
                                    Мои отзывы
                                </h5>
                                @if($recentReviews->count() > 0)
                                    <div class="list-group list-group-flush">
                                        @foreach($recentReviews as $review)
                                            <div class="list-group-item border-0 px-0 py-2">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0">
                                                        <div class="rating small">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                @if($i <= $review->rating)
                                                                    <i class="bi bi-star-fill text-warning"></i>
                                                                @else
                                                                    <i class="bi bi-star text-warning"></i>
                                                                @endif
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="mb-1">{{ Str::limit($review->comment, 80) }}</p>
                                                        <small class="text-muted">{{ $review->created_at->diffForHumans() }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-3">Вы еще не оставляли отзывы</p>
                                @endif
                                <div class="mt-3">
                                    @if($canReview)
                                        <a href="{{ route('reviews.create') }}" class="btn btn-outline-success w-100 mb-2">
                                            <i class="bi bi-plus-circle me-2"></i>Оставить отзыв
                                        </a>
                                    @endif
                                    <a href="{{ route('profile.reviews') }}" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-list-ul me-2"></i>Все отзывы
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Часто задаваемые вопросы -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-question-circle text-info me-2"></i>
                            Частые вопросы
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            @foreach($faqs as $index => $faq)
                                <div class="accordion-item border-0 mb-2">
                                    <h2 class="accordion-header" id="heading{{ $index }}">
                                        <button class="accordion-button collapsed bg-light" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}">
                                            {{ $faq->question }}
                                        </button>
                                    </h2>
                                    <div id="collapse{{ $index }}" class="accordion-collapse collapse"
                                         data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            {{ $faq->answer }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('faqs.index') }}" class="btn btn-link">
                                Все вопросы <i class="bi bi-arrow-right ms-1"></i>
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
        .avatar {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .card {
            border-radius: 12px;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .table th {
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }

        .accordion-button {
            border-radius: 8px !important;
            font-weight: 500;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0d6efd;
        }

        .list-group-item {
            border-left: none;
            border-right: none;
        }

        .list-group-item:first-child {
            border-top: none;
            padding-top: 0;
        }

        .list-group-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Пометить уведомления как прочитанные при клике
            document.querySelectorAll('.list-group-item').forEach(item => {
                item.addEventListener('click', function() {
                    const badge = this.querySelector('.badge');
                    if (badge) {
                        badge.remove();

                        // Отправить запрос на сервер для пометки как прочитанного
                        const notificationId = this.dataset.notificationId;
                        if (notificationId) {
                            fetch(`/notifications/${notificationId}/read`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json'
                                }
                            });
                        }
                    }
                });
            });

            // Обновление счетчика уведомлений в реальном времени
            function updateNotificationCount() {
                fetch('{{ route("notifications.unread-count") }}')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.badge[data-notification-count]');
                        if (badge) {
                            badge.textContent = data.count;
                            if (data.count === 0) {
                                badge.style.display = 'none';
                            } else {
                                badge.style.display = 'inline-flex';
                            }
                        }
                    });
            }

            // Обновлять каждые 60 секунд
            setInterval(updateNotificationCount, 60000);

            // Автоматическое обновление статуса бронирований
            function checkBookingStatus() {
                fetch('{{ route("profile.bookings.check-status") }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.updated) {
                            // Если есть обновления, показать уведомление
                            const alert = document.createElement('div');
                            alert.className = 'alert alert-info alert-dismissible fade show';
                            alert.innerHTML = `
                            <i class="bi bi-info-circle me-2"></i>
                            Статус ваших бронирований обновлен
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                            document.querySelector('.container.py-5').prepend(alert);

                            // Обновить данные на странице
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        }
                    });
            }

            // Проверять каждые 2 минуты
            setInterval(checkBookingStatus, 120000);
        });
    </script>
@endpush
