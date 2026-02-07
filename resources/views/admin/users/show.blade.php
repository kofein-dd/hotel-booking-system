@extends('admin.layouts.app')

@section('title', 'Просмотр пользователя')
@section('page-title', 'Просмотр пользователя: ' . $user->name)

@section('content')
    <div class="row">
        <div class="col-md-4">
            <!-- Карточка пользователя -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    @if($user->avatar)
                        <img src="{{ asset($user->avatar) }}" alt="{{ $user->name }}"
                             class="rounded-circle mb-3" width="120" height="120">
                    @else
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 120px; height: 120px; font-size: 48px;">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                    @endif

                    <h4>{{ $user->name }}</h4>
                    <p class="text-muted">{{ $user->email }}</p>

                    <div class="mb-3">
                        @php
                            $roleColors = [
                                'admin' => 'danger',
                                'moderator' => 'warning',
                                'user' => 'success',
                            ];
                        @endphp
                        <span class="badge bg-{{ $roleColors[$user->role] ?? 'secondary' }}">
                        @if($user->role == 'admin') Администратор
                            @elseif($user->role == 'moderator') Модератор
                            @else Пользователь
                            @endif
                    </span>

                        @if($user->isBanned())
                            <span class="badge bg-danger">Заблокирован</span>
                        @else
                            <span class="badge bg-{{ $user->status == 'active' ? 'success' : 'secondary' }}">
                            {{ $user->status == 'active' ? 'Активен' : 'Неактивен' }}
                        </span>
                        @endif
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Редактировать
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Информация о пользователе -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Информация о пользователе</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>ID:</strong>
                            <p>{{ $user->id }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <strong>Телефон:</strong>
                            <p>{{ $user->phone ?? 'Не указан' }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <strong>Дата регистрации:</strong>
                            <p>{{ $user->created_at->format('d.m.Y H:i') }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <strong>Последнее обновление:</strong>
                            <p>{{ $user->updated_at->format('d.m.Y H:i') }}</p>
                        </div>

                        @if($user->banned_until)
                            <div class="col-md-6 mb-3">
                                <strong>Заблокирован до:</strong>
                                <p class="text-danger">
                                    {{ $user->banned_until->format('d.m.Y H:i') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Статистика пользователя -->
                    <h5 class="mt-4 mb-3">Статистика</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>{{ $user->bookings->count() }}</h3>
                                    <p class="text-muted mb-0">Бронирований</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>{{ $user->reviews->count() }}</h3>
                                    <p class="text-muted mb-0">Отзывов</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>{{ $user->payments->sum('amount') }} ₽</h3>
                                    <p class="text-muted mb-0">Всего потрачено</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Последние бронирования пользователя -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Последние бронирования</h5>
                    <a href="{{ route('admin.bookings.index') }}?user={{ $user->id }}"
                       class="btn btn-sm btn-outline-primary">
                        Все бронирования
                    </a>
                </div>
                <div class="card-body">
                    @if($user->bookings->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Номер</th>
                                    <th>Даты</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($user->bookings->take(5) as $booking)
                                    <tr>
                                        <td>#{{ $booking->confirmation_number }}</td>
                                        <td>{{ $booking->room->room_number }}</td>
                                        <td>
                                            {{ $booking->check_in->format('d.m') }} -
                                            {{ $booking->check_out->format('d.m') }}
                                        </td>
                                        <td>{{ number_format($booking->total_price, 0, '.', ' ') }} ₽</td>
                                        <td>
                                            @php
                                                $statusClass = [
                                                    'pending' => 'warning',
                                                    'confirmed' => 'success',
                                                    'cancelled' => 'danger',
                                                    'completed' => 'info',
                                                ];
                                            @endphp
                                            <span class="badge bg-{{ $statusClass[$booking->status] ?? 'secondary' }}">
                                            {{ $booking->status }}
                                        </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">У пользователя нет бронирований</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Назад к списку
        </a>
    </div>
@endsection
