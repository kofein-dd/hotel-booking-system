@extends('layouts.app')

@section('title', 'Главная - Бронирование отелей')

@section('content')
    <div class="container-fluid p-0">
        <!-- Герой секция -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="display-4 mb-4">Найдите идеальный номер для отдыха</h1>
                        <p class="lead mb-4">Бронируйте лучшие отели и номера по выгодным ценам</p>
                        <a href="{{ route('rooms.index') }}" class="btn btn-primary btn-lg">Найти номер</a>
                    </div>
                    <div class="col-lg-6">
                        <!-- Здесь может быть слайдер или большое изображение -->
                    </div>
                </div>
            </div>
        </section>

        <!-- Популярные номера -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="row mb-4">
                    <div class="col">
                        <h2 class="section-title">Популярные номера</h2>
                        <p class="section-subtitle">Самые востребованные номера наших отелей</p>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('rooms.index') }}" class="btn btn-outline-primary">Все номера</a>
                    </div>
                </div>

                <div class="row">
                    @forelse($rooms as $room)
                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                            <div class="card h-100 room-card">
                                @if($room->images->count() > 0)
                                    <img src="{{ $room->images->first()->url ?? '/images/default-room.jpg' }}"
                                         class="card-img-top" alt="{{ $room->name }}" style="height: 200px; object-fit: cover;">
                                @else
                                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center"
                                         style="height: 200px;">
                                        <span class="text-white">Нет изображения</span>
                                    </div>
                                @endif

                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">{{ $room->name }}</h5>
                                    <p class="card-text text-muted mb-2">
                                        <small>
                                            <i class="fas fa-hotel"></i> {{ $room->hotel->name ?? 'Отель не указан' }}
                                        </small>
                                    </p>
                                    <p class="card-text flex-grow-1">
                                        {{ Str::limit($room->description, 100) }}
                                    </p>

                                    <div class="room-features mb-3">
                                        @if($room->capacity)
                                            <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-user"></i> {{ $room->capacity }} чел.
                                </span>
                                        @endif

                                        @if($room->size)
                                            <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-expand"></i> {{ $room->size }} м²
                                </span>
                                        @endif

                                        @if($room->view)
                                            <span class="badge bg-light text-dark">
                                    <i class="fas fa-binoculars"></i> {{ $room->view }}
                                </span>
                                        @endif
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div class="price">
                                            <strong class="text-primary h4">{{ number_format($room->price_per_night, 0, ',', ' ') }} ₽</strong>
                                            <small class="text-muted d-block">за ночь</small>
                                        </div>
                                        <a href="{{ route('rooms.show', $room->slug) }}" class="btn btn-primary">
                                            Подробнее
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-info">
                                Номера временно недоступны. Пожалуйста, зайдите позже.
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Выделенные номера -->
        @if($featuredRooms->count() > 0)
            <section class="py-5">
                <div class="container">
                    <div class="row mb-4">
                        <div class="col">
                            <h2 class="section-title">Рекомендуем</h2>
                            <p class="section-subtitle">Специально отобранные номера для вашего комфорта</p>
                        </div>
                    </div>

                    <div class="row">
                        @foreach($featuredRooms as $room)
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 featured-room-card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-star me-2"></i> Рекомендуем
                                    </div>

                                    @if($room->images->count() > 0)
                                        <img src="{{ $room->images->first()->url ?? '/images/default-room.jpg' }}"
                                             class="card-img-top" alt="{{ $room->name }}" style="height: 250px; object-fit: cover;">
                                    @endif

                                    <div class="card-body">
                                        <h5 class="card-title">{{ $room->name }}</h5>
                                        <p class="card-text">{{ Str::limit($room->description, 150) }}</p>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="text-primary h4">{{ number_format($room->price_per_night, 0, ',', ' ') }} ₽</strong>
                                                <small class="text-muted d-block">за ночь</small>
                                            </div>
                                            <a href="{{ route('rooms.show', $room->slug) }}" class="btn btn-primary">
                                                Забронировать
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <!-- Наши отели -->
        @if($hotels->count() > 0)
            <section class="py-5 bg-light">
                <div class="container">
                    <div class="row mb-4">
                        <div class="col">
                            <h2 class="section-title">Наши отели</h2>
                            <p class="section-subtitle">Лучшие отели для вашего отдыха</p>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('hotels.index') }}" class="btn btn-outline-primary">Все отели</a>
                        </div>
                    </div>

                    <div class="row">
                        @foreach($hotels as $hotel)
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card h-100 hotel-card">
                                    @if($hotel->images->count() > 0)
                                        <img src="{{ $hotel->images->first()->url ?? '/images/default-hotel.jpg' }}"
                                             class="card-img-top" alt="{{ $hotel->name }}" style="height: 200px; object-fit: cover;">
                                    @endif

                                    <div class="card-body">
                                        <h5 class="card-title">{{ $hotel->name }}</h5>
                                        <p class="card-text text-muted">
                                            <i class="fas fa-map-marker-alt"></i> {{ $hotel->city }}, {{ $hotel->country }}
                                        </p>

                                        <div class="hotel-rating mb-3">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= $hotel->stars)
                                                    <i class="fas fa-star text-warning"></i>
                                                @else
                                                    <i class="far fa-star text-muted"></i>
                                                @endif
                                            @endfor
                                            <span class="ms-2">{{ $hotel->stars }} звезд</span>
                                        </div>

                                        <a href="{{ route('hotels.show', $hotel->slug) }}" class="btn btn-outline-primary w-100">
                                            Подробнее об отеле
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <!-- Удобства -->
        @if($facilities->count() > 0)
            <section class="py-5">
                <div class="container">
                    <div class="row mb-4">
                        <div class="col">
                            <h2 class="section-title">Наши удобства</h2>
                            <p class="section-subtitle">Все для вашего комфортного отдыха</p>
                        </div>
                    </div>

                    <div class="row">
                        @foreach($facilities as $facility)
                            <div class="col-md-3 col-sm-6 mb-4">
                                <div class="text-center facility-item">
                                    @if($facility->icon)
                                        <div class="facility-icon mb-3">
                                            <i class="{{ $facility->icon }} fa-3x text-primary"></i>
                                        </div>
                                    @endif
                                    <h5>{{ $facility->name }}</h5>
                                    @if($facility->description)
                                        <p class="text-muted small">{{ Str::limit($facility->description, 80) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <!-- Призыв к действию -->
        <section class="py-5 bg-primary text-white">
            <div class="container text-center">
                <h2 class="mb-4">Готовы к незабываемому отдыху?</h2>
                <p class="lead mb-4">Забронируйте номер прямо сейчас и получите лучшие условия!</p>
                <a href="{{ route('rooms.index') }}" class="btn btn-light btn-lg">Начать бронирование</a>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
            url('/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: #6c757d;
            margin-bottom: 2rem;
        }

        .room-card, .hotel-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .room-card:hover, .hotel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .featured-room-card {
            border-width: 2px;
        }

        .facility-item {
            padding: 20px;
            border-radius: 10px;
            background: #f8f9fa;
            transition: background 0.3s;
        }

        .facility-item:hover {
            background: #e9ecef;
        }
    </style>
@endpush
