@extends('layouts.admin')

@section('page-title', 'Панель управления')
@section('breadcrumbs')
    <li class="breadcrumb-item active">Панель управления</li>
@endsection

@section('content')
    <div class="container-fluid">
        <!-- Статистика вверху -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $todayBookings = \App\Models\Booking::whereDate('created_at', today())->count() }}</h3>
                        <p>Бронирований сегодня</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <a href="{{ route('admin.bookings.index') }}" class="small-box-footer">
                        Подробнее <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $todayRevenue = \App\Models\Payment::whereDate('created_at', today())->sum('amount') }}<sup style="font-size: 20px">₽</sup></h3>
                        <p>Доход сегодня</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <a href="{{ route('admin.payments.index') }}" class="small-box-footer">
                        Подробнее <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $totalUsers = \App\Models\User::count() }}</h3>
                        <p>Всего пользователей</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <a href="{{ route('admin.users.index') }}" class="small-box-footer">
                        Подробнее <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $pendingBookings = \App\Models\Booking::where('status', 'pending')->count() }}</h3>
                        <p>Ожидают подтверждения</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="small-box-footer">
                        Подробнее <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Основной контент -->
        <div class="row">
            <!-- Левая колонка -->
            <div class="col-lg-8">
                <!-- График бронирований за последние 7 дней -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Бронирования за последние 7 дней</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="bookingsChart" height="250"></canvas>
                    </div>
                </div>

                <!-- Последние бронирования -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Последние бронирования</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-list"></i> Все бронирования
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th style="width: 10px">ID</th>
                                <th>Пользователь</th>
                                <th>Номер</th>
                                <th>Даты</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th style="width: 40px"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach(\App\Models\Booking::with(['user', 'room'])->latest()->take(10)->get() as $booking)
                                <tr>
                                    <td>#{{ $booking->id }}</td>
                                    <td>
                                        <a href="{{ route('admin.users.show', $booking->user_id) }}">
                                            {{ $booking->user->name }}
                                        </a>
                                    </td>
                                    <td>{{ $booking->room->name ?? 'Удален' }}</td>
                                    <td>
                                        {{ $booking->check_in->format('d.m') }} - {{ $booking->check_out->format('d.m.Y') }}
                                    </td>
                                    <td>{{ number_format($booking->total_price, 0, '', ' ') }} ₽</td>
                                    <td>
                                        @if($booking->status == 'pending')
                                            <span class="badge badge-warning">Ожидание</span>
                                        @elseif($booking->status == 'confirmed')
                                            <span class="badge badge-success">Подтверждено</span>
                                        @elseif($booking->status == 'cancelled')
                                            <span class="badge badge-danger">Отменено</span>
                                        @elseif($booking->status == 'completed')
                                            <span class="badge badge-info">Завершено</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Правая колонка -->
            <div class="col-lg-4">
                <!-- Статистика по номерам -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Статистика по номерам</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-group">
                            Одноместные
                            <span class="float-right">
                            <b>{{ $singleRooms = \App\Models\Room::where('type', 'single')->count() }}</b>/{{ $totalRooms = \App\Models\Room::count() }}
                        </span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary"
                                     style="width: {{ $totalRooms > 0 ? ($singleRooms/$totalRooms)*100 : 0 }}%"></div>
                            </div>
                        </div>

                        <div class="progress-group">
                            Двухместные
                            <span class="float-right">
                            <b>{{ $doubleRooms = \App\Models\Room::where('type', 'double')->count() }}</b>/{{ $totalRooms }}
                        </span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success"
                                     style="width: {{ $totalRooms > 0 ? ($doubleRooms/$totalRooms)*100 : 0 }}%"></div>
                            </div>
                        </div>

                        <div class="progress-group">
                            Люкс
                            <span class="float-right">
                            <b>{{ $luxuryRooms = \App\Models\Room::where('type', 'luxury')->count() }}</b>/{{ $totalRooms }}
                        </span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-warning"
                                     style="width: {{ $totalRooms > 0 ? ($luxuryRooms/$totalRooms)*100 : 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Последние отзывы -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Последние отзывы</h3>
                    </div>
                    <div class="card-body">
                        @foreach(\App\Models\Review::with('user')->latest()->take(5)->get() as $review)
                            <div class="post">
                                <div class="user-block">
                                    <img class="img-circle img-bordered-sm"
                                         src="{{ $review->user->avatar_url ?? asset('img/default-avatar.png') }}"
                                         alt="User Image">
                                    <span class="username">
                                    <a href="#">{{ $review->user->name }}</a>
                                </span>
                                    <span class="description">
                                    {{ $review->created_at->diffForHumans() }}
                                </span>
                                </div>
                                <p>
                                    {{ Str::limit($review->comment, 100) }}
                                </p>
                                <div class="rating">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review->rating)
                                            <i class="fas fa-star text-warning"></i>
                                        @else
                                            <i class="far fa-star"></i>
                                        @endif
                                    @endfor
                                </div>
                            </div>
                            <hr>
                        @endforeach
                    </div>
                </div>

                <!-- Системная информация -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Системная информация</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li>
                                <i class="fas fa-server mr-2"></i>
                                Laravel: {{ app()->version() }}
                            </li>
                            <li>
                                <i class="fas fa-database mr-2"></i>
                                MySQL: {{ DB::select('select version() as version')[0]->version }}
                            </li>
                            <li>
                                <i class="fas fa-memory mr-2"></i>
                                Память: {{ round(memory_get_usage() / 1024 / 1024, 2) }} MB
                            </li>
                            <li>
                                <i class="fas fa-clock mr-2"></i>
                                Время: {{ now()->format('H:i:s d.m.Y') }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(function () {
            // Данные для графика
            const bookingsData = @json($bookingsData ?? []);
            const dates = Object.keys(bookingsData);
            const counts = Object.values(bookingsData);

            // Создание графика
            const ctx = document.getElementById('bookingsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Бронирования',
                        data: counts,
                        backgroundColor: 'rgba(60, 141, 188, 0.2)',
                        borderColor: 'rgba(60, 141, 188, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
        });
    </script>
@endpush
