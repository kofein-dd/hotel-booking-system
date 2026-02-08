@extends('layouts.app')

@section('title', 'Мои бронирования')

@section('content')
    <div class="container py-5">
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item"><a href="{{ route('profile.index') }}">Личный кабинет</a></li>
                <li class="breadcrumb-item active" aria-current="page">Мои бронирования</li>
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
                                    <i class="bi bi-calendar-check text-primary me-2"></i>
                                    Мои бронирования
                                </h1>
                                <p class="text-muted mb-0 mt-2 small">
                                    Всего бронирований: {{ $bookings->total() }}
                                </p>
                            </div>
                            <div>
                                <a href="{{ route('rooms.index') }}" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Новое бронирование
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- Фильтры -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('profile.bookings') }}"
                                       class="btn btn-outline-secondary {{ !request('status') ? 'active' : '' }}">
                                        Все ({{ $totalBookings }})
                                    </a>
                                    <a href="{{ route('profile.bookings', ['status' => 'active']) }}"
                                       class="btn btn-outline-primary {{ request('status') == 'active' ? 'active' : '' }}">
                                        Активные ({{ $activeCount }})
                                    </a>
                                    <a href="{{ route('profile.bookings', ['status' => 'pending']) }}"
                                       class="btn btn-outline-warning {{ request('status') == 'pending' ? 'active' : '' }}">
                                        Ожидают ({{ $pendingCount }})
                                    </a>
                                    <a href="{{ route('profile.bookings', ['status' => 'confirmed']) }}"
                                       class="btn btn-outline-success {{ request('status') == 'confirmed' ? 'active' : '' }}">
                                        Подтвержденные ({{ $confirmedCount }})
                                    </a>
                                    <a href="{{ route('profile.bookings', ['status' => 'completed']) }}"
                                       class="btn btn-outline-info {{ request('status') == 'completed' ? 'active' : '' }}">
                                        Завершенные ({{ $completedCount }})
                                    </a>
                                    <a href="{{ route('profile.bookings', ['status' => 'cancelled']) }}"
                                       class="btn btn-outline-danger {{ request('status') == 'cancelled' ? 'active' : '' }}">
                                        Отмененные ({{ $cancelledCount }})
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <form method="GET" class="d-flex">
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Поиск по номеру..."
                                           value="{{ request('search') }}">
                                    <button type="submit" class="btn btn-outline-secondary ms-2">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Сортировка -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="small text-muted">
                                Показано {{ $bookings->firstItem() }}-{{ $bookings->lastItem() }} из {{ $bookings->total() }}
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-sort-down me-2"></i>
                                    {{ request('sort') == 'oldest' ? 'Старые сначала' : 'Новые сначала' }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                                            <i class="bi bi-sort-down-alt me-2"></i>Новые сначала
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                                            <i class="bi bi-sort-up me-2"></i>Старые сначала
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Список бронирований -->
                        @if($bookings->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Бронирование</th>
                                        <th>Даты</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th>Действия</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($bookings as $booking)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @if($booking->room->photos && count($booking->room->photos) > 0)
                                                        <img src="{{ asset($booking->room->photos[0]) }}"
                                                             alt="{{ $booking->room->name }}"
                                                             class="rounded me-3" style="width: 60px; height: 40px; object-fit: cover;">
                                                    @endif
                                                    <div>
                                                        <strong>#{{ $booking->id }} - {{ $booking->room->name }}</strong>
                                                        <div class="small text-muted">
                                                            {{ $booking->guests_count }} {{ trans_choice('гость|гостя|гостей', $booking->guests_count) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $booking->check_in->format('d.m.Y') }}</div>
                                                <div class="small text-muted">
                                                    {{ $booking->nights_count }} {{ trans_choice('ночь|ночи|ночей', $booking->nights_count) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold">{{ number_format($booking->total_price, 0, '', ' ') }} ₽</div>
                                                @if($booking->discount)
                                                    <div class="small text-success">
                                                        <i class="bi bi-percent me-1"></i>Скидка {{ $booking->discount->value }}%
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($booking->status == 'confirmed')
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Подтверждено
                                                    </span>
                                                    @if($booking->check_in->isFuture())
                                                        <div class="small text-muted mt-1">
                                                            Заезд через {{ $booking->check_in->diffForHumans() }}
                                                        </div>
                                                    @endif
                                                @elseif($booking->status == 'pending')
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-clock me-1"></i>Ожидает подтверждения
                                                    </span>
                                                    <div class="small text-muted mt-1">
                                                        Создано {{ $booking->created_at->diffForHumans() }}
                                                    </div>
                                                @elseif($booking->status == 'cancelled')
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Отменено
                                                    </span>
                                                    @if($booking->cancellation_date)
                                                        <div class="small text-muted mt-1">
                                                            {{ $booking->cancellation_date->format('d.m.Y') }}
                                                        </div>
                                                    @endif
                                                @elseif($booking->status == 'completed')
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-check2-all me-1"></i>Завершено
                                                    </span>
                                                    <div class="small text-muted mt-1">
                                                        {{ $booking->check_out->format('d.m.Y') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('profile.bookings.show', $booking->id) }}"
                                                       class="btn btn-outline-primary" title="Просмотр">
                                                        <i class="bi bi-eye"></i>
                                                    </a>

                                                    @if($booking->canBeModified())
                                                        <a href="{{ route('profile.bookings.edit', $booking->id) }}"
                                                           class="btn btn-outline-warning" title="Изменить">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    @endif

                                                    @if($booking->canBeCancelled())
                                                        <button type="button" class="btn btn-outline-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#cancelModal{{ $booking->id }}"
                                                                title="Отменить">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    @endif

                                                    @if($booking->canLeaveReview())
                                                        <a href="{{ route('reviews.create', ['booking_id' => $booking->id]) }}"
                                                           class="btn btn-outline-success" title="Оставить отзыв">
                                                            <i class="bi bi-star"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Модальное окно отмены -->
                                        <div class="modal fade" id="cancelModal{{ $booking->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-warning text-dark">
                                                        <h5 class="modal-title">
                                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                            Отмена бронирования #{{ $booking->id }}
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Вы уверены, что хотите отменить бронирование?</p>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle-fill me-2"></i>
                                                            @if($booking->isRefundable())
                                                                При отмене будет возвращено {{ number_format($booking->refund_amount, 0, '', ' ') }} ₽
                                                            @else
                                                                Отмена невозможна менее чем за 24 часа до заезда
                                                            @endif
                                                        </div>
                                                        <form method="POST" action="{{ route('profile.bookings.cancel', $booking->id) }}"
                                                              id="cancelForm{{ $booking->id }}">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="mb-3">
                                                                <label for="cancellation_reason" class="form-label">
                                                                    Причина отмены (необязательно):
                                                                </label>
                                                                <textarea class="form-control" id="cancellation_reason"
                                                                          name="cancellation_reason" rows="3"></textarea>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            Отмена
                                                        </button>
                                                        <button type="submit" form="cancelForm{{ $booking->id }}"
                                                                class="btn btn-danger">
                                                            <i class="bi bi-x-circle me-2"></i>Отменить бронирование
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Пагинация -->
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="small text-muted">
                                    Страница {{ $bookings->currentPage() }} из {{ $bookings->lastPage() }}
                                </div>
                                <nav aria-label="Навигация по страницам">
                                    {{ $bookings->withQueryString()->links() }}
                                </nav>
                            </div>
                        @else
                            <!-- Сообщение об отсутствии бронирований -->
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                                </div>
                                <h4 class="fw-bold mb-3">Бронирований не найдено</h4>
                                <p class="text-muted mb-4">
                                    @if(request('status') || request('search'))
                                        Попробуйте изменить параметры поиска
                                    @else
                                        У вас еще нет бронирований. Самое время найти идеальный номер!
                                    @endif
                                </p>
                                <a href="{{ route('rooms.index') }}" class="btn btn-primary btn-lg">
                                    <i class="bi bi-search me-2"></i>Найти номер
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Статистика -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-cash-stack text-success fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ number_format($totalSpent, 0, '', ' ') }} ₽</h3>
                                <p class="text-muted mb-0">Всего потрачено</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-moon text-primary fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ $totalNights }}</h3>
                                <p class="text-muted mb-0">Всего ночей</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-people text-warning fs-1 mb-3"></i>
                                <h3 class="fw-bold mb-2">{{ $totalGuests }}</h3>
                                <p class="text-muted mb-0">Всего гостей</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- График активности -->
                @if($bookingsChartData)
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-bar-chart text-info me-2"></i>
                                Активность бронирований
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="bookingsChart" height="100"></canvas>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .btn-outline-secondary.active {
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
        }

        .table th {
            font-weight: 600;
            color: #495057;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
        }

        .badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            border: none;
            color: #495057;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // График активности бронирований
            @if($bookingsChartData)
            const ctx = document.getElementById('bookingsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: @json($bookingsChartData['labels']),
                    datasets: [{
                        label: 'Количество бронирований',
                        data: @json($bookingsChartData['data']),
                        backgroundColor: 'rgba(13, 110, 253, 0.2)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            @endif

            // Фильтрация по статусу
            const statusButtons = document.querySelectorAll('[href*="status="]');
            statusButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.classList.contains('active')) {
                        e.preventDefault();
                        window.location.href = '{{ route("profile.bookings") }}';
                    }
                });
            });

            // Подтверждение отмены бронирования
            const cancelForms = document.querySelectorAll('[id^="cancelForm"]');
            cancelForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Вы уверены, что хотите отменить бронирование?')) {
                        e.preventDefault();
                    }
                });
            });

            // Экспорт бронирований
            const exportButton = document.createElement('button');
            exportButton.className = 'btn btn-outline-secondary mt-3';
            exportButton.innerHTML = '<i class="bi bi-download me-2"></i>Экспорт в Excel';
            exportButton.addEventListener('click', function() {
                window.location.href = '{{ route("profile.bookings.export") }}?' + window.location.search.substring(1);
            });

            const cardHeader = document.querySelector('.card-header');
            if (cardHeader) {
                cardHeader.appendChild(exportButton);
            }

            // Автоматическое обновление статуса
            function updateBookingStatuses() {
                fetch('{{ route("profile.bookings.check-updates") }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.updated) {
                            const alert = document.createElement('div');
                            alert.className = 'alert alert-info alert-dismissible fade show';
                            alert.innerHTML = `
                            <i class="bi bi-info-circle me-2"></i>
                            Статус бронирований обновлен
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                            document.querySelector('.card-body').prepend(alert);

                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        }
                    });
            }

            // Обновлять каждую минуту
            setInterval(updateBookingStatuses, 60000);

            // Быстрый поиск
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;

                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.form.submit();
                    }, 500);
                });
            }
        });
    </script>
@endpush
