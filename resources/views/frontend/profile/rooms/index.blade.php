@extends('layouts.app')

@section('title', 'Номера и цены')

@section('content')
    <div class="container-fluid px-0">
        <!-- Hero секция -->
        <div class="hero-section position-relative overflow-hidden">
            <div class="container">
                <div class="row min-vh-50 py-5 align-items-center">
                    <div class="col-lg-8 text-white">
                        <h1 class="display-4 fw-bold mb-4">Наши номера</h1>
                        <p class="lead mb-4">Выберите идеальный номер для вашего отдыха у моря</p>

                        <!-- Быстрый поиск -->
                        <div class="card bg-white bg-opacity-10 border-0 rounded-3 p-3">
                            <div class="card-body">
                                <form action="{{ route('rooms.index') }}" method="GET" id="quickSearchForm">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label text-white small mb-1">Заезд</label>
                                            <input type="date" class="form-control" name="check_in"
                                                   value="{{ request('check_in', date('Y-m-d', strtotime('+1 day'))) }}"
                                                   min="{{ date('Y-m-d') }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label text-white small mb-1">Выезд</label>
                                            <input type="date" class="form-control" name="check_out"
                                                   value="{{ request('check_out', date('Y-m-d', strtotime('+3 days'))) }}"
                                                   min="{{ date('Y-m-d', strtotime('+2 days')) }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label text-white small mb-1">Гости</label>
                                            <select class="form-select" name="guests">
                                                @for($i = 1; $i <= 6; $i++)
                                                    <option value="{{ $i }}"
                                                        {{ request('guests', 2) == $i ? 'selected' : '' }}>
                                                        {{ $i }} {{ trans_choice('человек|человека', $i) }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label text-white small mb-1">Номера</label>
                                            <select class="form-select" name="rooms">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <option value="{{ $i }}"
                                                        {{ request('rooms', 1) == $i ? 'selected' : '' }}>
                                                        {{ $i }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100 h-100">
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

            <!-- Фоновое изображение -->
            <div class="hero-background">
                <div class="background-overlay"></div>
                <div class="background-image"
                     style="background-image: url('{{ asset('img/rooms-hero.jpg') }}')"></div>
            </div>
        </div>

        <!-- Основной контент -->
        <div class="container py-5">
            <div class="row">
                <!-- Фильтры -->
                <div class="col-lg-3 mb-4">
                    <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-funnel text-primary me-2"></i>
                                Фильтры
                            </h5>
                        </div>

                        <div class="card-body">
                            <!-- Поиск -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Поиск по названию</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Название номера..."
                                           value="{{ request('search') }}"
                                           id="searchInput">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Тип номера -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Тип номера</label>
                                <div class="list-group list-group-flush">
                                    @foreach($roomTypes as $type => $count)
                                        <label class="list-group-item border-0 px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="type[]" value="{{ $type }}"
                                                    {{ in_array($type, (array)request('type', [])) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ $type }}</span>
                                            </div>
                                            <span class="badge bg-secondary">{{ $count }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Удобства -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Удобства</label>
                                <div class="list-group list-group-flush">
                                    @foreach($amenities as $amenity => $count)
                                        <label class="list-group-item border-0 px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="amenities[]" value="{{ $amenity }}"
                                                    {{ in_array($amenity, (array)request('amenities', [])) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ $amenity }}</span>
                                            </div>
                                            <span class="badge bg-secondary">{{ $count }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Цена за ночь -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    Цена за ночь:
                                    <span id="priceRangeValue">{{ request('price_min', 0) }} - {{ request('price_max', 50000) }} ₽</span>
                                </label>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <input type="number" class="form-control" name="price_min"
                                               placeholder="Мин" min="0" max="100000"
                                               value="{{ request('price_min', 0) }}" id="priceMin">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control" name="price_max"
                                               placeholder="Макс" min="0" max="100000"
                                               value="{{ request('price_max', 50000) }}" id="priceMax">
                                    </div>
                                </div>
                                <input type="range" class="form-range" min="0" max="100000"
                                       step="1000" id="priceSlider"
                                       value="{{ request('price_max', 50000) }}">
                            </div>

                            <!-- Количество гостей -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Количество гостей</label>
                                <div class="row g-2">
                                    @foreach([1, 2, 3, 4, 5, 6] as $capacity)
                                        <div class="col-4">
                                            <label class="d-block text-center">
                                                <input type="checkbox" class="btn-check"
                                                       name="capacity[]" value="{{ $capacity }}"
                                                       {{ in_array($capacity, (array)request('capacity', [])) ? 'checked' : '' }}
                                                       autocomplete="off">
                                                <span class="btn btn-outline-secondary w-100">
                                                {{ $capacity }}
                                                <i class="bi bi-person ms-1"></i>
                                            </span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Сортировка -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Сортировка</label>
                                <select class="form-select" name="sort" id="sortSelect">
                                    <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>
                                        Цена (по возрастанию)
                                    </option>
                                    <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>
                                        Цена (по убыванию)
                                    </option>
                                    <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>
                                        Название (А-Я)
                                    </option>
                                    <option value="name_desc" {{ request('sort') == 'name_desc' ? 'selected' : '' }}>
                                        Название (Я-А)
                                    </option>
                                    <option value="rating_desc" {{ request('sort') == 'rating_desc' ? 'selected' : '' }}>
                                        Рейтинг (высокий)
                                    </option>
                                    <option value="capacity_asc" {{ request('sort') == 'capacity_asc' ? 'selected' : '' }}>
                                        Вместимость (малая)
                                    </option>
                                    <option value="capacity_desc" {{ request('sort') == 'capacity_desc' ? 'selected' : '' }}>
                                        Вместимость (большая)
                                    </option>
                                </select>
                            </div>

                            <!-- Кнопки фильтра -->
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" id="applyFilters">
                                    <i class="bi bi-check-circle me-2"></i>Применить фильтры
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Сбросить все
                                </button>
                            </div>
                        </div>

                        <!-- Спецпредложения -->
                        @if($specialOffers->count() > 0)
                            <div class="card-footer bg-light border-0 p-3">
                                <h6 class="fw-bold mb-3">
                                    <i class="bi bi-percent text-success me-2"></i>
                                    Спецпредложения
                                </h6>
                                <div class="list-group list-group-flush">
                                    @foreach($specialOffers as $offer)
                                        <div class="list-group-item border-0 px-0 py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <span class="badge bg-success">{{ $offer->discount }}%</span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <small class="fw-bold">{{ $offer->title }}</small>
                                                    <div class="small text-muted">{{ $offer->description }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Список номеров -->
                <div class="col-lg-9">
                    <!-- Заголовок и статистика -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="h4 fw-bold mb-1">Доступные номера</h2>
                            <p class="text-muted mb-0">
                                Найдено {{ $rooms->total() }} {{ trans_choice('номер|номера|номеров', $rooms->total()) }}
                                @if(request('check_in') && request('check_out'))
                                    на {{ request('check_in') }} - {{ request('check_out') }}
                                @endif
                            </p>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-grid me-2"></i>
                                {{ request('view', 'grid') == 'grid' ? 'Сетка' : 'Список' }}
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="#" data-view="grid">
                                        <i class="bi bi-grid me-2"></i>Сетка
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" data-view="list">
                                        <i class="bi bi-list-ul me-2"></i>Список
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Сообщения о фильтрах -->
                    @if(request()->anyFilled(['type', 'amenities', 'price_min', 'price_max', 'capacity', 'search']))
                        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-funnel fs-4 me-3"></i>
                                <div>
                                    <strong>Применены фильтры:</strong>
                                    <div class="mt-1">
                                        @foreach(request()->all() as $key => $value)
                                            @if(in_array($key, ['type', 'amenities', 'capacity']) && is_array($value))
                                                @foreach($value as $item)
                                                    <span class="badge bg-primary me-1 mb-1">{{ $key }}: {{ $item }}</span>
                                                @endforeach
                                            @elseif(in_array($key, ['price_min', 'price_max', 'search']) && $value)
                                                <span class="badge bg-primary me-1 mb-1">{{ $key }}: {{ $value }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <a href="{{ route('rooms.index') }}" class="btn btn-sm btn-outline-info ms-auto">
                                    <i class="bi bi-x-circle me-1"></i>Очистить
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Список номеров -->
                    @if($rooms->count() > 0)
                        <div class="row {{ request('view', 'grid') == 'grid' ? 'g-4' : '' }}" id="roomsContainer">
                            @foreach($rooms as $room)
                                @if(request('view', 'grid') == 'grid')
                                    <!-- Отображение сеткой -->
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card room-card border-0 shadow-sm h-100">
                                            <!-- Галерея изображений -->
                                            <div class="room-gallery position-relative">
                                                @if($room->photos && count($room->photos) > 0)
                                                    <div id="carousel{{ $room->id }}" class="carousel slide" data-bs-ride="carousel">
                                                        <div class="carousel-inner">
                                                            @foreach($room->photos as $index => $photo)
                                                                <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                                                                    <img src="{{ asset($photo) }}"
                                                                         class="d-block w-100 room-image"
                                                                         alt="{{ $room->name }}"
                                                                         style="height: 200px; object-fit: cover;">
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        @if(count($room->photos) > 1)
                                                            <button class="carousel-control-prev" type="button"
                                                                    data-bs-target="#carousel{{ $room->id }}" data-bs-slide="prev">
                                                                <span class="carousel-control-prev-icon"></span>
                                                            </button>
                                                            <button class="carousel-control-next" type="button"
                                                                    data-bs-target="#carousel{{ $room->id }}" data-bs-slide="next">
                                                                <span class="carousel-control-next-icon"></span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="room-image bg-secondary"
                                                         style="height: 200px;"></div>
                                                @endif

                                                <!-- Бейджи -->
                                                <div class="position-absolute top-0 end-0 p-3">
                                                    @if($room->discount)
                                                        <span class="badge bg-danger">
                                                        -{{ $room->discount->value }}%
                                                    </span>
                                                    @endif
                                                    @if($room->is_popular)
                                                        <span class="badge bg-warning mt-1">
                                                        <i class="bi bi-star-fill me-1"></i>Популярный
                                                    </span>
                                                    @endif
                                                </div>

                                                <!-- Кнопка избранного -->
                                                <button class="btn btn-sm btn-light position-absolute top-0 start-0 m-3 rounded-circle favorite-btn"
                                                        data-room-id="{{ $room->id }}"
                                                        title="{{ auth()->check() && auth()->user()->hasFavorite($room->id) ? 'Удалить из избранного' : 'Добавить в избранное' }}">
                                                    <i class="bi {{ auth()->check() && auth()->user()->hasFavorite($room->id) ? 'bi-heart-fill text-danger' : 'bi-heart' }}"></i>
                                                </button>
                                            </div>

                                            <!-- Информация о номере -->
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h5 class="card-title fw-bold mb-1">{{ $room->name }}</h5>
                                                        <p class="text-muted small mb-0">
                                                            <i class="bi bi-people me-1"></i>
                                                            До {{ $room->capacity }} гостей •
                                                            <i class="bi bi-door-closed me-1"></i>
                                                            {{ $room->size }} м²
                                                        </p>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="h5 fw-bold text-primary mb-0">
                                                            @if($room->discount)
                                                                <span class="text-decoration-line-through text-muted small">
                                                                {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                                            </span><br>
                                                                {{ number_format($room->final_price, 0, '', ' ') }} ₽
                                                            @else
                                                                {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                                            @endif
                                                        </div>
                                                        <small class="text-muted">за ночь</small>
                                                    </div>
                                                </div>

                                                <p class="card-text text-muted mb-3">
                                                    {{ Str::limit($room->description, 100) }}
                                                </p>

                                                <!-- Рейтинг -->
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="rating small">
                                                        @for($i = 1; $i <= 5; $i++)
                                                            @if($i <= floor($room->average_rating))
                                                                <i class="bi bi-star-fill text-warning"></i>
                                                            @elseif($i <= $room->average_rating)
                                                                <i class="bi bi-star-half text-warning"></i>
                                                            @else
                                                                <i class="bi bi-star text-warning"></i>
                                                            @endif
                                                        @endfor
                                                    </div>
                                                    <span class="ms-2 small text-muted">
                                                    ({{ $room->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $room->reviews_count) }})
                                                </span>
                                                </div>

                                                <!-- Удобства -->
                                                <div class="room-amenities mb-3">
                                                    @foreach($room->amenities->take(3) as $amenity)
                                                        <span class="badge bg-light text-dark me-1 mb-1">
                                                        <i class="bi bi-check-circle me-1"></i>{{ $amenity }}
                                                    </span>
                                                    @endforeach
                                                    @if(count($room->amenities) > 3)
                                                        <span class="badge bg-light text-dark">
                                                        +{{ count($room->amenities) - 3 }}
                                                    </span>
                                                    @endif
                                                </div>

                                                <!-- Кнопки действий -->
                                                <div class="d-grid gap-2">
                                                    <a href="{{ route('rooms.show', $room->id) }}"
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye me-2"></i>Подробнее
                                                    </a>
                                                    @if($room->is_available)
                                                        <a href="{{ route('booking.step1', ['room_id' => $room->id]) }}"
                                                           class="btn btn-primary">
                                                            <i class="bi bi-calendar-check me-2"></i>Забронировать
                                                        </a>
                                                    @else
                                                        <button class="btn btn-secondary" disabled>
                                                            <i class="bi bi-x-circle me-2"></i>Недоступно
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <!-- Отображение списком -->
                                    <div class="col-12 mb-4">
                                        <div class="card room-card-list border-0 shadow-sm">
                                            <div class="row g-0">
                                                <div class="col-md-4">
                                                    @if($room->photos && count($room->photos) > 0)
                                                        <img src="{{ asset($room->photos[0]) }}"
                                                             class="img-fluid rounded-start h-100 w-100"
                                                             alt="{{ $room->name }}"
                                                             style="object-fit: cover; min-height: 250px;">
                                                    @else
                                                        <div class="bg-secondary h-100 w-100 d-flex align-items-center justify-content-center">
                                                            <i class="bi bi-image text-white" style="font-size: 3rem;"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="card-body h-100 d-flex flex-column">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <h5 class="card-title fw-bold mb-1">{{ $room->name }}</h5>
                                                                <p class="text-muted small mb-0">
                                                                    <i class="bi bi-people me-1"></i>
                                                                    До {{ $room->capacity }} гостей •
                                                                    <i class="bi bi-door-closed me-1"></i>
                                                                    {{ $room->size }} м² •
                                                                    <i class="bi bi-geo-alt me-1"></i>{{ $room->view }}
                                                                </p>
                                                            </div>
                                                            <div class="text-end">
                                                                @if($room->discount)
                                                                    <span class="badge bg-danger">
                                                                    -{{ $room->discount->value }}%
                                                                </span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <p class="card-text text-muted mb-3 flex-grow-1">
                                                            {{ Str::limit($room->description, 200) }}
                                                        </p>

                                                        <div class="row align-items-end">
                                                            <div class="col-md-6">
                                                                <!-- Рейтинг -->
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <div class="rating">
                                                                        @for($i = 1; $i <= 5; $i++)
                                                                            @if($i <= floor($room->average_rating))
                                                                                <i class="bi bi-star-fill text-warning"></i>
                                                                            @elseif($i <= $room->average_rating)
                                                                                <i class="bi bi-star-half text-warning"></i>
                                                                            @else
                                                                                <i class="bi bi-star text-warning"></i>
                                                                            @endif
                                                                        @endfor
                                                                    </div>
                                                                    <span class="ms-2 small text-muted">
                                                                    ({{ $room->reviews_count }} отзывов)
                                                                </span>
                                                                </div>

                                                                <!-- Удобства -->
                                                                <div class="room-amenities">
                                                                    @foreach($room->amenities->take(4) as $amenity)
                                                                        <span class="badge bg-light text-dark me-1 mb-1">
                                                                        <i class="bi bi-check-circle me-1"></i>{{ $amenity }}
                                                                    </span>
                                                                    @endforeach
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div class="text-end">
                                                                        <div class="h4 fw-bold text-primary mb-0">
                                                                            @if($room->discount)
                                                                                <span class="text-decoration-line-through text-muted small">
                                                                                {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                                                            </span><br>
                                                                                {{ number_format($room->final_price, 0, '', ' ') }} ₽
                                                                            @else
                                                                                {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                                                            @endif
                                                                        </div>
                                                                        <small class="text-muted">за ночь</small>
                                                                    </div>

                                                                    <div class="btn-group">
                                                                        <a href="{{ route('rooms.show', $room->id) }}"
                                                                           class="btn btn-outline-primary">
                                                                            <i class="bi bi-eye"></i>
                                                                        </a>
                                                                        @if($room->is_available)
                                                                            <a href="{{ route('booking.step1', ['room_id' => $room->id]) }}"
                                                                               class="btn btn-primary">
                                                                                <i class="bi bi-calendar-check me-2"></i>Бронировать
                                                                            </a>
                                                                        @else
                                                                            <button class="btn btn-secondary" disabled>
                                                                                <i class="bi bi-x-circle"></i>
                                                                            </button>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <!-- Пагинация -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="small text-muted">
                                Показано {{ $rooms->firstItem() }}-{{ $rooms->lastItem() }} из {{ $rooms->total() }}
                            </div>
                            <nav aria-label="Навигация по страницам">
                                {{ $rooms->withQueryString()->links() }}
                            </nav>
                        </div>
                    @else
                        <!-- Сообщение об отсутствии номеров -->
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-door-closed text-muted" style="font-size: 4rem;"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Номера не найдены</h4>
                            <p class="text-muted mb-4">
                                @if(request()->anyFilled(['check_in', 'check_out']))
                                    На выбранные даты нет доступных номеров. Попробуйте изменить даты или параметры поиска.
                                @else
                                    По вашему запросу ничего не найдено. Попробуйте изменить параметры фильтрации.
                                @endif
                            </p>
                            <div class="d-flex justify-content-center gap-3">
                                <button type="button" class="btn btn-outline-primary" id="clearSearchDates">
                                    <i class="bi bi-calendar-x me-2"></i>Очистить даты
                                </button>
                                <button type="button" class="btn btn-primary" id="resetAllFilters">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Сбросить фильтры
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Информация о бронировании -->
                    <div class="card border-0 shadow-sm mt-5">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="fw-bold mb-2">Нужна помощь с выбором?</h5>
                                    <p class="text-muted mb-0">
                                        Наши консультанты помогут подобрать идеальный номер для вашего отдыха
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="{{ route('contact.index') }}" class="btn btn-primary me-2">
                                        <i class="bi bi-chat-dots me-2"></i>Задать вопрос
                                    </a>
                                    <a href="tel:+78005553535" class="btn btn-outline-primary">
                                        <i class="bi bi-telephone me-2"></i>Позвонить
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно быстрого просмотра -->
    <div class="modal fade" id="quickViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Контент будет загружаться через AJAX -->
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .hero-section {
            min-height: 50vh;
            position: relative;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.8) 0%, rgba(0, 86, 179, 0.8) 100%);
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

        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            z-index: 0;
        }

        .room-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 12px;
            overflow: hidden;
        }

        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
        }

        .room-card-list {
            transition: transform 0.3s;
        }

        .room-card-list:hover {
            transform: translateX(5px);
        }

        .room-image {
            border-radius: 12px 12px 0 0;
        }

        .favorite-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .favorite-btn:hover {
            background-color: #fff !important;
            transform: scale(1.1);
        }

        .rating {
            color: #ffc107;
        }

        .room-amenities .badge {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
        }

        .sticky-top {
            z-index: 100;
        }

        .btn-check:checked + .btn-outline-secondary {
            background-color: #6c757d;
            color: white;
        }

        #priceSlider {
            height: 6px;
            border-radius: 3px;
        }

        #priceSlider::-webkit-slider-thumb {
            width: 20px;
            height: 20px;
            background: #0d6efd;
            border-radius: 50%;
        }

        @media (max-width: 768px) {
            .hero-section {
                min-height: 40vh;
            }

            .room-card-list .col-md-4 {
                height: 200px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Переключение вида (сетка/список)
            const viewLinks = document.querySelectorAll('[data-view]');
            viewLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const view = this.dataset.view;

                    // Сохранить в localStorage
                    localStorage.setItem('roomsView', view);

                    // Обновить URL
                    const url = new URL(window.location);
                    url.searchParams.set('view', view);
                    window.location.href = url.toString();
                });
            });

            // Восстановить вид из localStorage
            const savedView = localStorage.getItem('roomsView');
            if (savedView && !new URLSearchParams(window.location.search).has('view')) {
                const url = new URL(window.location);
                url.searchParams.set('view', savedView);
                window.history.replaceState({}, '', url);
            }

            // Слайдер цены
            const priceSlider = document.getElementById('priceSlider');
            const priceMin = document.getElementById('priceMin');
            const priceMax = document.getElementById('priceMax');
            const priceRangeValue = document.getElementById('priceRangeValue');

            if (priceSlider && priceMin && priceMax) {
                function updatePriceRange() {
                    const min = parseInt(priceMin.value) || 0;
                    const max = parseInt(priceMax.value) || 50000;
                    priceRangeValue.textContent = `${min} - ${max} ₽`;
                    priceSlider.value = max;

                    // Обновить min/max у ползунка
                    priceSlider.min = min;
                }

                priceMin.addEventListener('input', updatePriceRange);
                priceMax.addEventListener('input', updatePriceRange);
                priceSlider.addEventListener('input', function() {
                    priceMax.value = this.value;
                    updatePriceRange();
                });

                updatePriceRange();
            }

            // Применение фильтров
            document.getElementById('applyFilters')?.addEventListener('click', function() {
                const formData = new FormData();
                const form = document.querySelector('#quickSearchForm');

                // Собрать данные из всех полей фильтров
                const filters = [
                    'search', 'type[]', 'amenities[]', 'price_min', 'price_max',
                    'capacity[]', 'sort', 'check_in', 'check_out', 'guests', 'rooms'
                ];

                filters.forEach(filter => {
                    const elements = document.querySelectorAll(`[name="${filter}"]`);
                    elements.forEach(el => {
                        if (el.type === 'checkbox' || el.type === 'radio') {
                            if (el.checked) formData.append(filter.replace('[]', ''), el.value);
                        } else if (el.value) {
                            formData.append(filter.replace('[]', ''), el.value);
                        }
                    });
                });

                // Построить URL с параметрами
                const params = new URLSearchParams();
                for (let [key, value] of formData) {
                    params.append(key, value);
                }

                window.location.href = '{{ route("rooms.index") }}?' + params.toString();
            });

            // Сброс фильтров
            document.getElementById('resetFilters')?.addEventListener('click', function() {
                window.location.href = '{{ route("rooms.index") }}';
            });

            document.getElementById('resetAllFilters')?.addEventListener('click', function() {
                window.location.href = '{{ route("rooms.index") }}';
            });

            // Очистка поиска
            document.getElementById('clearSearch')?.addEventListener('click', function() {
                document.getElementById('searchInput').value = '';
            });

            // Очистка дат
            document.getElementById('clearSearchDates')?.addEventListener('click', function() {
                const url = new URL(window.location);
                url.searchParams.delete('check_in');
                url.searchParams.delete('check_out');
                window.location.href = url.toString();
            });

            // Добавление в избранное
            document.querySelectorAll('.favorite-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const roomId = this.dataset.roomId;
                    const icon = this.querySelector('i');

                    if (!{{ auth()->check() ? 'true' : 'false' }}) {
                        // Перенаправить на страницу входа
                        window.location.href = '{{ route("login") }}?redirect=' + encodeURIComponent(window.location.href);
                        return;
                    }

                    fetch('{{ route("rooms.toggle-favorite") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ room_id: roomId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Обновить иконку
                                if (data.action === 'added') {
                                    icon.classList.remove('bi-heart');
                                    icon.classList.add('bi-heart-fill', 'text-danger');
                                    this.title = 'Удалить из избранного';
                                    showToast('Номер добавлен в избранное', 'success');
                                } else {
                                    icon.classList.remove('bi-heart-fill', 'text-danger');
                                    icon.classList.add('bi-heart');
                                    this.title = 'Добавить в избранное';
                                    showToast('Номер удален из избранного', 'info');
                                }
                            }
                        });
                });
            });

            // Быстрый просмотр номера
            document.querySelectorAll('.room-card, .room-card-list').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Проверить, не был ли клик по кнопке
                    if (e.target.closest('a, button, .favorite-btn')) {
                        return;
                    }

                    const roomId = this.querySelector('.favorite-btn')?.dataset.roomId;
                    if (roomId) {
                        // Загрузить данные номера через AJAX
                        fetch(`/rooms/${roomId}/quick-view`)
                            .then(response => response.text())
                            .then(html => {
                                const modal = document.getElementById('quickViewModal');
                                modal.querySelector('.modal-content').innerHTML = html;
                                new bootstrap.Modal(modal).show();
                            });
                    }
                });
            });

            // Автоматический поиск при вводе
            let searchTimeout;
            document.getElementById('searchInput')?.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('applyFilters').click();
                }, 1000);
            });

            // Функция показа тостов
            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

                document.body.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();

                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            }

            // Проверка доступности номеров в реальном времени
            function checkAvailability() {
                const checkIn = document.querySelector('input[name="check_in"]')?.value;
                const checkOut = document.querySelector('input[name="check_out"]')?.value;

                if (!checkIn || !checkOut) return;

                fetch('{{ route("rooms.check-availability") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        check_in: checkIn,
                        check_out: checkOut
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        // Обновить статусы номеров
                        data.rooms.forEach(room => {
                            const roomElement = document.querySelector(`[data-room-id="${room.id}"]`)?.closest('.room-card, .room-card-list');
                            if (roomElement) {
                                const bookBtn = roomElement.querySelector('.btn-primary');
                                if (bookBtn && !room.is_available) {
                                    bookBtn.disabled = true;
                                    bookBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Недоступно';
                                    bookBtn.className = 'btn btn-secondary';
                                }
                            }
                        });
                    });
            }

            // Проверять доступность при изменении дат
            document.querySelectorAll('input[name="check_in"], input[name="check_out"]').forEach(input => {
                input.addEventListener('change', checkAvailability);
            });
        });
    </script>
@endpush
