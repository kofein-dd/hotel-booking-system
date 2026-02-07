@extends('admin.layouts.app')

@section('title', 'Дашборд')
@section('page-title', 'Дашборд')

@section('content')
    <div class="row mb-4">
        <!-- Статистические карточки -->
        <div class="col-md-3 mb-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">{{ $stats['total_users'] }}</div>
                        <div class="stat-label">Всего пользователей</div>
                    </div>
                    <div class="stat-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">{{ $stats['active_bookings'] }}</div>
                        <div class="stat-label">Активные брони</div>
                    </div>
                    <div class="stat-icon text-success">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">{{ $stats['available_rooms'] }}/{{ $stats['total_rooms'] }}</div>
                        <div class="stat-label">Свободные номера</div>
                    </div>
                    <div class="stat-icon text-info">
                        <i class="fas fa-bed"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">{{ number_format($stats['revenue_month'], 0, '.', ' ') }} ₽</div>
                        <div class="stat-label">Доход за месяц</div>
                    </div>
                    <div class="stat-icon text-warning">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Статистика бронирований -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Статистика бронирований</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="badge badge-pending me-2" style="width: 20px; height: 20px;"></div>
                                <span>Ожидание: {{ $bookingStatuses['pending'] }}</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="badge badge-confirmed me-2" style="width: 20px; height: 20px;"></div>
                                <span>Подтверждено: {{ $bookingStatuses['confirmed'] }}</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="badge badge-cancelled me-2" style="width: 20px; height: 20px;"></div>
                                <span>Отменено: {{ $bookingStatuses['cancelled'] }}</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="badge badge-completed me-2" style="width: 20px; height: 20px;"></div>
                                <span>Завершено: {{ $bookingStatuses['completed'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Сегодня -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Сегодня</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-sign-in-alt text-primary me-2"></i>
                                <div>
                                    <div class="fw-bold">{{ $stats['today_checkins'] }}</div>
                                    <small class="text-muted">Заезды</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-sign-out-alt text-success me-2"></i>
                                <div>
                                    <div class="fw-bold">{{ $stats['today_checkouts'] }}</div>
                                    <small class="text-muted">Выезды</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-money-bill-wave text-warning me-2"></i>
                                <div>
                                    <div class="fw-bold">{{ number_format($stats['revenue_today'], 0, '.', ' ') }} ₽</div>
                                    <small class="text-muted">Доход сегодня</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Последние бронирования -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Последние бронирования</h5>
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-primary">Все бронирования</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Номер</th>
                                <th>Даты</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recentBookings as $booking)
                                <tr>
                                    <td>#{{ $booking->confirmation_number }}</td>
                                    <td>{{ $booking->user->name }}</td>
                                    <td>{{ $booking->room->room_number }}</td>
                                    <td>
                                        {{ $booking->check_in->format('d.m') }} - {{ $booking->check_out->format('d.m') }}
                                        <br><small>{{ $booking->nights }} ночей</small>
                                    </td>
                                    <td>{{ number_format($booking->total_price, 0, '.', ' ') }} ₽</td>
                                    <td>
                                        @php
                                            $statusClass = [
                                                'pending' => 'badge-pending',
                                                'confirmed' => 'badge-confirmed',
                                                'cancelled' => 'badge-cancelled',
                                                'completed' => 'badge-completed',
                                                'no_show' => 'badge-danger',
                                                'refunded' => 'badge-warning',
                                            ][$booking->status] ?? 'badge-secondary';
                                        @endphp
                                        <span class="badge {{ $statusClass }} badge-status">
                                        @if($booking->status == 'pending') Ожидание
                                            @elseif($booking->status == 'confirmed') Подтверждено
                                            @elseif($booking->status == 'cancelled') Отменено
                                            @elseif($booking->status == 'completed') Завершено
                                            @else {{ $booking->status }}
                                            @endif
                                    </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Нет бронирований</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Последние пользователи -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Новые пользователи</h5>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary">Все пользователи</a>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @forelse($recentUsers as $user)
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        @if($user->avatar)
                                            <img src="{{ asset($user->avatar) }}" alt="{{ $user->name }}" class="rounded-circle" width="40" height="40">
                                        @else
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                {{ substr($user->name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0">{{ $user->name }}</h6>
                                        <small class="text-muted">{{ $user->email }}</small>
                                        <div>
                                    <span class="badge bg-{{ $user->role == 'admin' ? 'danger' : ($user->role == 'moderator' ? 'warning' : 'success') }}">
                                        {{ $user->role == 'admin' ? 'Админ' : ($user->role == 'moderator' ? 'Модератор' : 'Пользователь') }}
                                    </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-center">Нет пользователей</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Доход по месяцам -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Доход по месяцам (последние 6 месяцев)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                @foreach($monthlyRevenue as $month => $revenue)
                                    <th>{{ $month }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                @foreach($monthlyRevenue as $revenue)
                                    <td class="fw-bold">{{ number_format($revenue, 0, '.', ' ') }} ₽</td>
                                @endforeach
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
