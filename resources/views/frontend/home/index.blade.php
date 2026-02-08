@extends('layouts.app')

@section('title', 'Главная - Отель у Моря')

@section('content')
    <div class="container">
        <!-- Герой секция -->
        <div class="hero-section text-center py-5">
            <h1 class="display-4">Добро пожаловать в Отель у Моря</h1>
            <p class="lead">Комфортный отдых на берегу моря с видом на закат</p>

            @auth
                <a href="{{ route('booking.step1') }}" class="btn btn-primary btn-lg">Забронировать номер</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Войти</a>
                <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg">Регистрация</a>
            @endauth
        </div>

        <!-- Быстрый поиск -->
        <div class="quick-search bg-light p-4 rounded mb-5">
            <h3 class="text-center mb-4">Найти и забронировать номер</h3>
            <form action="{{ route('search.rooms') }}" method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="check_in" class="form-label">Заезд</label>
                        <input type="date" class="form-control" id="check_in" name="check_in" required>
                    </div>
                    <div class="col-md-3">
                        <label for="check_out" class="form-label">Выезд</label>
                        <input type="date" class="form-control" id="check_out" name="check_out" required>
                    </div>
                    <div class="col-md-2">
                        <label for="guests" class="form-label">Гостей</label>
                        <select class="form-select" id="guests" name="guests">
                            <option value="1">1 гость</option>
                            <option value="2" selected>2 гостя</option>
                            <option value="3">3 гостя</option>
                            <option value="4">4 гостя</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Найти номера</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Популярные номера -->
        <div class="featured-rooms mb-5">
            <h2 class="text-center mb-4">Популярные номера</h2>
            <div class="row">
                @foreach($featuredRooms ?? [] as $room)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            @if($room->images && count($room->images) > 0)
                                <img src="{{ asset('storage/' . $room->images[0]) }}" class="card-img-top" alt="{{ $room->name }}">
                            @else
                                <img src="{{ asset('images/default-room.jpg') }}" class="card-img-top" alt="Номер">
                            @endif
                            <div class="card-body">
                                <h5 class="card-title">{{ $room->name }}</h5>
                                <p class="card-text">{{ Str::limit($room->description, 100) }}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 text-primary">{{ number_format($room->price_per_night, 0, ',', ' ') }} ₽/ночь</span>
                                    <a href="{{ route('rooms.show', $room) }}" class="btn btn-sm btn-outline-primary">Подробнее</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="text-center">
                <a href="{{ route('rooms.index') }}" class="btn btn-outline-primary">Все номера</a>
            </div>
        </div>

        <!-- Об отеле -->
        <div class="about-hotel mb-5">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>Наш отель</h2>
                    <p>Расположенный на первой береговой линии, наш отель предлагает комфортабельные номера с видом на море, современные удобства и высокий уровень сервиса.</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-wifi"></i> Бесплатный Wi-Fi</li>
                        <li><i class="fas fa-swimming-pool"></i> Бассейн с подогревом</li>
                        <li><i class="fas fa-utensils"></i> Ресторан с морской кухней</li>
                        <li><i class="fas fa-spa"></i> СПА-центр</li>
                    </ul>
                    <a href="{{ route('hotel.about') }}" class="btn btn-primary">Подробнее об отеле</a>
                </div>
                <div class="col-md-6">
                    <img src="{{ asset('images/hotel-exterior.jpg') }}" alt="Отель" class="img-fluid rounded">
                </div>
            </div>
        </div>

        <!-- Отзывы -->
        <div class="reviews mb-5">
            <h2 class="text-center mb-4">Отзывы гостей</h2>
            <div class="row">
                @foreach($reviews ?? [] as $review)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rating">
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= $review->rating)
                                                <i class="fas fa-star text-warning"></i>
                                            @else
                                                <i class="far fa-star text-warning"></i>
                                            @endif
                                        @endfor
                                    </div>
                                </div>
                                <p class="card-text">"{{ Str::limit($review->comment, 150) }}"</p>
                                <div class="d-flex align-items-center mt-3">
                                    <div class="me-3">
                                        <strong>{{ $review->user->name ?? 'Аноним' }}</strong>
                                        <div class="text-muted small">{{ $review->created_at->format('d.m.Y') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @auth
                <div class="text-center">
                    <a href="{{ route('profile.reviews.create') }}" class="btn btn-outline-primary">Оставить отзыв</a>
                </div>
            @endauth
        </div>

        <!-- Контакты -->
        <div class="contacts bg-light p-5 rounded text-center">
            <h2 class="mb-4">Контакты</h2>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <i class="fas fa-map-marker-alt fa-2x mb-3 text-primary"></i>
                    <h5>Адрес</h5>
                    <p>Краснодарский край, г. Сочи, ул. Приморская, 123</p>
                </div>
                <div class="col-md-4 mb-3">
                    <i class="fas fa-phone fa-2x mb-3 text-primary"></i>
                    <h5>Телефон</h5>
                    <p>+7 (862) 123-45-67</p>
                </div>
                <div class="col-md-4 mb-3">
                    <i class="fas fa-envelope fa-2x mb-3 text-primary"></i>
                    <h5>Email</h5>
                    <p>info@hotel-by-sea.ru</p>
                </div>
            </div>
            <a href="{{ route('contact.index') }}" class="btn btn-primary mt-3">Связаться с нами</a>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Установка минимальной даты для поиска
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('check_in').min = today;

            // Обновление минимальной даты выезда при выборе даты заезда
            document.getElementById('check_in').addEventListener('change', function() {
                const checkOut = document.getElementById('check_out');
                checkOut.min = this.value;
                if (checkOut.value && checkOut.value < this.value) {
                    checkOut.value = this.value;
                }
            });
        });
    </script>
@endsection
