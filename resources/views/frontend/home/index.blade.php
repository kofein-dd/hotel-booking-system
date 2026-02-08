@extends('layouts.app')

@section('title', 'Главная - ' . config('app.name', 'Отель у моря'))

@section('content')
    <!-- Hero секция -->
    <section class="hero-section position-relative overflow-hidden">
        <div class="container">
            <div class="row align-items-center min-vh-100 py-5">
                <div class="col-lg-6 text-white">
                    <h1 class="display-4 fw-bold mb-4 animate__animated animate__fadeInUp">
                        Отдых у моря вашей мечты
                    </h1>
                    <p class="lead mb-5 animate__animated animate__fadeInUp animate__delay-1s">
                        {{ $hotel->description ?? 'Роскошный отель с видом на море. Идеальное место для отдыха, релаксации и создания незабываемых воспоминаний.' }}
                    </p>
                    <div class="d-flex flex-wrap gap-3 animate__animated animate__fadeInUp animate__delay-2s">
                        <a href="#booking" class="btn btn-primary btn-lg px-5 py-3">
                            <i class="bi bi-calendar-check me-2"></i>Забронировать сейчас
                        </a>
                        <a href="#rooms" class="btn btn-outline-light btn-lg px-5 py-3">
                            <i class="bi bi-camera me-2"></i>Посмотреть номера
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Фоновое видео/изображение -->
        <div class="hero-background">
            <div class="background-overlay"></div>
            @if($hotel->video_url ?? false)
                <video autoplay muted loop class="background-video">
                    <source src="{{ $hotel->video_url }}" type="video/mp4">
                </video>
            @else
                <div class="background-image"
                     style="background-image: url('{{ asset($hotel->main_photo ?? 'img/hero-bg.jpg') }}')">
                </div>
            @endif
        </div>
    </section>

    <!-- Преимущества -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col">
                    <h2 class="fw-bold mb-3">Почему выбирают нас</h2>
                    <p class="text-muted">Мы заботимся о вашем комфорте и незабываемом отдыхе</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-icon mb-3">
                            <i class="bi bi-geo-alt-fill text-primary fs-1"></i>
                        </div>
                        <h5 class="card-title fw-bold">Идеальное расположение</h5>
                        <p class="card-text text-muted">
                            Прямой выход к морю, в 50 метрах от пляжа
                        </p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-icon mb-3">
                            <i class="bi bi-star-fill text-warning fs-1"></i>
                        </div>
                        <h5 class="card-title fw-bold">Высокий рейтинг</h5>
                        <p class="card-text text-muted">
                            4.8/5 по отзывам наших гостей
                        </p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-icon mb-3">
                            <i class="bi bi-wifi text-success fs-1"></i>
                        </div>
                        <h5 class="card-title fw-bold">Все удобства</h5>
                        <p class="card-text text-muted">
                            Wi-Fi, кондиционер, бассейн, SPA
                        </p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-icon mb-3">
                            <i class="bi bi-shield-check text-info fs-1"></i>
                        </div>
                        <h5 class="card-title fw-bold">Безопасная оплата</h5>
                        <p class="card-text text-muted">
                            Гарантия возврата средств при отмене
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Бронирование -->
    <section id="booking" class="py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col">
                    <h2 class="fw-bold text-center mb-3">Быстрое бронирование</h2>
                    <p class="text-muted text-center">Найдите и забронируйте идеальный номер</p>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-4">
                            <form action="{{ route('rooms.index') }}" method="GET" id="bookingForm">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-calendar3 me-2"></i>Заезд
                                        </label>
                                        <input type="date" class="form-control"
                                               name="check_in" id="check_in"
                                               min="{{ date('Y-m-d') }}"
                                               value="{{ request('check_in', date('Y-m-d', strtotime('+1 day'))) }}"
                                               required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-calendar3 me-2"></i>Выезд
                                        </label>
                                        <input type="date" class="form-control"
                                               name="check_out" id="check_out"
                                               min="{{ date('Y-m-d', strtotime('+2 days')) }}"
                                               value="{{ request('check_out', date('Y-m-d', strtotime('+3 days'))) }}"
                                               required>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-people me-2"></i>Гости
                                        </label>
                                        <select class="form-select" name="guests" id="guests">
                                            @for($i = 1; $i <= 6; $i++)
                                                <option value="{{ $i }}"
                                                    {{ request('guests', 2) == $i ? 'selected' : '' }}>
                                                    {{ $i }} {{ trans_choice('человек|человека', $i) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-door-closed me-2"></i>Номера
                                        </label>
                                        <select class="form-select" name="rooms" id="rooms">
                                            @for($i = 1; $i <= 5; $i++)
                                                <option value="{{ $i }}"
                                                    {{ request('rooms', 1) == $i ? 'selected' : '' }}>
                                                    {{ $i }} {{ trans_choice('номер|номера|номеров', $i) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100 py-3">
                                            <i class="bi bi-search me-2"></i>Найти
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Популярные номера -->
    <section id="rooms" class="py-5 bg-light">
        <div class="container">
            <div class="row mb-5">
                <div class="col">
                    <h2 class="fw-bold text-center mb-3">Наши номера</h2>
                    <p class="text-muted text-center">Выберите идеальный номер для вашего отдыха</p>
                </div>
            </div>

            <div class="row g-4">
                @foreach($rooms as $room)
                    <div class="col-md-4">
                        <div class="card room-card border-0 shadow-sm h-100 overflow-hidden">
                            @if($room->photos && count($room->photos) > 0)
                                <div class="room-image"
                                     style="background-image: url('{{ asset($room->photos[0]) }}')">
                                    @if($room->discount)
                                        <span class="badge bg-danger position-absolute top-0 end-0 m-3">
                                        -{{ $room->discount->value }}%
                                    </span>
                                    @endif
                                </div>
                            @endif

                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title fw-bold mb-1">{{ $room->name }}</h5>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-people me-1"></i>
                                            До {{ $room->capacity }} гостей
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <div class="h4 fw-bold text-primary mb-0">
                                            {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                        </div>
                                        <small class="text-muted">за ночь</small>
                                    </div>
                                </div>

                                <p class="card-text text-muted mb-4">
                                    {{ Str::limit($room->description, 100) }}
                                </p>

                                <div class="room-amenities mb-4">
                                    @foreach($room->amenities as $amenity)
                                        <span class="badge bg-light text-dark me-1 mb-1">
                                        <i class="bi bi-check-circle me-1"></i>{{ $amenity }}
                                    </span>
                                    @endforeach
                                </div>

                                <div class="d-grid">
                                    <a href="{{ route('rooms.show', $room->id) }}"
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-eye me-2"></i>Подробнее
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-5">
                <a href="{{ route('rooms.index') }}" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-list me-2"></i>Посмотреть все номера
                </a>
            </div>
        </div>
    </section>

    <!-- Отзывы -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col">
                    <h2 class="fw-bold text-center mb-3">Отзывы гостей</h2>
                    <p class="text-muted text-center">Что говорят наши гости</p>
                </div>
            </div>

            <div class="row g-4">
                @foreach($reviews as $review)
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-4">
                                    <img src="{{ $review->user->avatar_url ?? asset('img/default-avatar.png') }}"
                                         alt="{{ $review->user->name }}"
                                         class="rounded-circle me-3" width="50" height="50">
                                    <div>
                                        <h6 class="fw-bold mb-0">{{ $review->user->name }}</h6>
                                        <small class="text-muted">
                                            {{ $review->created_at->diffForHumans() }}
                                        </small>
                                    </div>
                                </div>

                                <div class="rating mb-3">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review->rating)
                                            <i class="bi bi-star-fill text-warning"></i>
                                        @else
                                            <i class="bi bi-star text-warning"></i>
                                        @endif
                                    @endfor
                                </div>

                                <p class="card-text">
                                    "{{ Str::limit($review->comment, 150) }}"
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Контакты -->
    <section class="py-5 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h3 class="fw-bold mb-4">
                        <i class="bi bi-house-heart me-2"></i>{{ config('app.name') }}
                    </h3>
                    <p class="mb-4">
                        {{ $hotel->short_description ?? 'Роскошный отель для незабываемого отдыха у моря.' }}
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white fs-5">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="text-white fs-5">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="text-white fs-5">
                            <i class="bi bi-telegram"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="fw-bold mb-4">Контактная информация</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="bi bi-geo-alt me-2"></i>
                            {{ $hotel->address ?? 'ул. Морская, 123, Сочи, Россия' }}
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-telephone me-2"></i>
                            <a href="tel:+78005553535" class="text-white text-decoration-none">
                                {{ $hotel->phone ?? '+7 (800) 555-35-35' }}
                            </a>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-envelope me-2"></i>
                            <a href="mailto:info@hotel.ru" class="text-white text-decoration-none">
                                {{ $hotel->email ?? 'info@hotel.ru' }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-4">
                    <h5 class="fw-bold mb-4">Быстрые ссылки</h5>
                    <div class="row">
                        <div class="col-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <a href="{{ route('rooms.index') }}" class="text-white text-decoration-none">
                                        Номера
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="{{ route('faqs.index') }}" class="text-white text-decoration-none">
                                        FAQ
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="{{ route('contact.index') }}" class="text-white text-decoration-none">
                                        Контакты
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <a href="{{ route('pages.show', 'about') }}" class="text-white text-decoration-none">
                                        Об отеле
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="{{ route('pages.show', 'terms') }}" class="text-white text-decoration-none">
                                        Условия
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="{{ route('pages.show', 'privacy') }}" class="text-white text-decoration-none">
                                        Конфиденциальность
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <style>
        /* Hero секция */
        .hero-section {
            min-height: 100vh;
            position: relative;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .background-video,
        .background-image {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
        }

        .background-image {
            background-size: cover;
            background-position: center;
        }

        /* Карточки номеров */
        .room-card {
            transition: transform 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
        }

        .room-card:hover {
            transform: translateY(-10px);
        }

        .room-image {
            height: 250px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .room-amenities .badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.6rem;
        }

        /* Анимации */
        .animate__animated {
            animation-duration: 1s;
            animation-fill-mode: both;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .hero-section {
                min-height: 80vh;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .room-card {
                margin-bottom: 2rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                min-height: 70vh;
            }

            .hero-section h1 {
                font-size: 2rem;
            }

            .btn-lg {
                width: 100%;
                margin-bottom: 1rem;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Валидация дат в форме бронирования
            const checkIn = document.getElementById('check_in');
            const checkOut = document.getElementById('check_out');
            const bookingForm = document.getElementById('bookingForm');

            // Установка минимальных дат
            const today = new Date().toISOString().split('T')[0];
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().split('T')[0];

            if (checkIn) {
                checkIn.min = today;
                checkIn.value = tomorrowStr;

                checkIn.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const minCheckOut = new Date(selectedDate);
                    minCheckOut.setDate(minCheckOut.getDate() + 1);

                    if (checkOut) {
                        checkOut.min = minCheckOut.toISOString().split('T')[0];

                        // Если текущая дата выезда меньше минимальной
                        const checkOutDate = new Date(checkOut.value);
                        if (checkOutDate < minCheckOut) {
                            checkOut.value = minCheckOut.toISOString().split('T')[0];
                        }
                    }
                });
            }

            if (checkOut) {
                const initialMinDate = new Date();
                initialMinDate.setDate(initialMinDate.getDate() + 2);
                checkOut.min = initialMinDate.toISOString().split('T')[0];
                checkOut.value = initialMinDate.toISOString().split('T')[0];
            }

            // Плавная прокрутка к якорям
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;

                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        e.preventDefault();
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Подсчет стоимости при изменении дат
            function calculateStay() {
                if (checkIn && checkOut && checkIn.value && checkOut.value) {
                    const start = new Date(checkIn.value);
                    const end = new Date(checkOut.value);
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    // Можно добавить отображение количества ночей
                    console.log(`Период проживания: ${diffDays} ночей`);
                }
            }

            if (checkIn && checkOut) {
                checkIn.addEventListener('change', calculateStay);
                checkOut.addEventListener('change', calculateStay);
            }

            // Инициализация расчета
            calculateStay();
        });
    </script>
@endpush
