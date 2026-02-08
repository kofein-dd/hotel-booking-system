@extends('layouts.app')

@section('title', $room->name . ' - ' . config('app.name'))

@section('content')
    <div class="container py-5">
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item"><a href="{{ route('rooms.index') }}">Номера</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $room->name }}</li>
            </ol>
        </nav>

        <div class="row">
            <!-- Основной контент -->
            <div class="col-lg-8">
                <!-- Галерея изображений -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-0">
                        @if($room->photos && count($room->photos) > 0)
                            <div class="room-gallery">
                                <!-- Главное изображение -->
                                <div class="main-image mb-3">
                                    <img id="mainImage" src="{{ asset($room->photos[0]) }}"
                                         alt="{{ $room->name }}"
                                         class="img-fluid rounded-3 w-100"
                                         style="height: 400px; object-fit: cover;">
                                </div>

                                <!-- Миниатюры -->
                                <div class="thumbnails d-flex flex-wrap gap-2">
                                    @foreach($room->photos as $index => $photo)
                                        <div class="thumbnail {{ $index == 0 ? 'active' : '' }}"
                                             style="width: 80px; height: 60px; cursor: pointer;">
                                            <img src="{{ asset($photo) }}"
                                                 alt="{{ $room->name }} - фото {{ $index + 1 }}"
                                                 class="img-fluid rounded w-100 h-100 object-fit-cover"
                                                 data-full="{{ asset($photo) }}"
                                                 onclick="changeMainImage('{{ asset($photo) }}', this)">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="text-center py-5 bg-light rounded-3">
                                <i class="bi bi-image text-muted" style="font-size: 4rem;"></i>
                                <p class="text-muted mt-3">Изображения номера отсутствуют</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Бейджи и информация -->
                <div class="d-flex flex-wrap gap-3 mb-4">
                    @if($room->discount)
                        <span class="badge bg-danger fs-6 px-3 py-2">
                        <i class="bi bi-percent me-1"></i>-{{ $room->discount->value }}% СКИДКА
                    </span>
                    @endif

                    @if($room->is_popular)
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                        <i class="bi bi-star-fill me-1"></i>ПОПУЛЯРНЫЙ НОМЕР
                    </span>
                    @endif

                    @if($room->is_new)
                        <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-rocket-takeoff me-1"></i>НОВИНКА
                    </span>
                    @endif

                    <span class="badge bg-info fs-6 px-3 py-2">
                    <i class="bi bi-door-closed me-1"></i>{{ $room->size }} м²
                </span>
                </div>

                <!-- Заголовок и рейтинг -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h1 class="h2 fw-bold mb-2">{{ $room->name }}</h1>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rating">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($room->average_rating))
                                        <i class="bi bi-star-fill text-warning fs-5"></i>
                                    @elseif($i <= $room->average_rating)
                                        <i class="bi bi-star-half text-warning fs-5"></i>
                                    @else
                                        <i class="bi bi-star text-warning fs-5"></i>
                                    @endif
                                @endfor
                                <span class="ms-2 fw-bold">{{ number_format($room->average_rating, 1) }}</span>
                            </div>
                            <span class="text-muted">
                            ({{ $room->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $room->reviews_count) }})
                        </span>
                            <span class="text-muted">
                            <i class="bi bi-geo-alt me-1"></i>{{ $room->view }}
                        </span>
                        </div>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary" id="shareButton" title="Поделиться">
                            <i class="bi bi-share"></i>
                        </button>
                        <button class="btn btn-outline-danger favorite-btn"
                                data-room-id="{{ $room->id }}"
                                title="{{ auth()->check() && auth()->user()->hasFavorite($room->id) ? 'Удалить из избранного' : 'Добавить в избранное' }}">
                            <i class="bi {{ auth()->check() && auth()->user()->hasFavorite($room->id) ? 'bi-heart-fill' : 'bi-heart' }}"></i>
                        </button>
                    </div>
                </div>

                <!-- Описание -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-info-circle text-primary me-2"></i>
                            Описание номера
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="room-description">
                            {!! nl2br(e($room->description)) !!}
                        </div>
                    </div>
                </div>

                <!-- Удобства -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Удобства и услуги
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($room->amenities->chunk(ceil(count($room->amenities) / 3)) as $chunk)
                                <div class="col-md-4">
                                    <ul class="list-unstyled">
                                        @foreach($chunk as $amenity)
                                            <li class="mb-2">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                {{ $amenity }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Характеристики -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-list-columns text-info me-2"></i>
                            Характеристики номера
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tbody>
                                    <tr>
                                        <th width="200"><i class="bi bi-people me-2"></i>Вместимость:</th>
                                        <td>{{ $room->capacity }} {{ trans_choice('гость|гостя|гостей', $room->capacity) }}</td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-door-closed me-2"></i>Площадь:</th>
                                        <td>{{ $room->size }} м²</td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-bed me-2"></i>Тип кровати:</th>
                                        <td>{{ $room->bed_type }}</td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-window me-2"></i>Вид из окна:</th>
                                        <td>{{ $room->view }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tbody>
                                    <tr>
                                        <th width="200"><i class="bi bi-building me-2"></i>Этаж:</th>
                                        <td>{{ $room->floor }}</td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-smoke me-2"></i>Курение:</th>
                                        <td>{{ $room->smoking_allowed ? 'Разрешено' : 'Запрещено' }}</td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-pet me-2"></i>Животные:</th>
                                        <td>{{ $room->pets_allowed ? 'Разрешены' : 'Запрещены' }}</td>
                                    </tr>
                                    <tr>
                                        <th><i class="bi bi-wifi me-2"></i>Wi-Fi:</th>
                                        <td>{{ $room->wifi_speed ? $room->wifi_speed . ' Мбит/с' : 'Бесплатный' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Отзывы -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-star text-warning me-2"></i>
                                Отзывы гостей
                            </h5>
                            @if($canReview)
                                <a href="{{ route('reviews.create', ['room_id' => $room->id]) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil me-2"></i>Оставить отзыв
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if($reviews->count() > 0)
                            <!-- Общая статистика -->
                            <div class="row mb-4">
                                <div class="col-md-3 text-center">
                                    <div class="display-4 fw-bold text-primary">{{ number_format($room->average_rating, 1) }}</div>
                                    <div class="rating small mb-2">
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
                                    <div class="text-muted small">
                                        {{ $room->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $room->reviews_count) }}
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <!-- Распределение рейтингов -->
                                    @foreach([5, 4, 3, 2, 1] as $rating)
                                        @php
                                            $count = $room->reviews->where('rating', $rating)->count();
                                            $percentage = $room->reviews_count > 0 ? ($count / $room->reviews_count) * 100 : 0;
                                        @endphp
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="text-end" style="width: 30px;">
                                                <small>{{ $rating }}</small>
                                                <i class="bi bi-star-fill text-warning ms-1"></i>
                                            </div>
                                            <div class="progress flex-grow-1 mx-3" style="height: 8px;">
                                                <div class="progress-bar bg-warning"
                                                     style="width: {{ $percentage }}%"></div>
                                            </div>
                                            <div style="width: 40px;">
                                                <small class="text-muted">{{ $count }}</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Список отзывов -->
                            <div class="reviews-list">
                                @foreach($reviews as $review)
                                    <div class="review-item border-top pt-3 mt-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-3">
                                                    <img src="{{ $review->user->avatar_url ?? asset('img/default-avatar.png') }}"
                                                         alt="{{ $review->user->name }}"
                                                         class="rounded-circle" width="40" height="40">
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0">{{ $review->user->name }}</h6>
                                                    <small class="text-muted">{{ $review->created_at->format('d.m.Y') }}</small>
                                                </div>
                                            </div>
                                            <div class="rating">
                                                @for($i = 1; $i <= 5; $i++)
                                                    @if($i <= $review->rating)
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                    @else
                                                        <i class="bi bi-star text-warning"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <p class="mb-2">{{ $review->comment }}</p>

                                        @if($review->response)
                                            <div class="response alert alert-info mt-3">
                                                <div class="fw-bold mb-1">
                                                    <i class="bi bi-chat-square-text me-2"></i>Ответ администрации:
                                                </div>
                                                <p class="mb-0">{{ $review->response }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <!-- Пагинация отзывов -->
                            @if($reviews->hasPages())
                                <div class="mt-4">
                                    {{ $reviews->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4">
                                <i class="bi bi-chat-square-text text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">Пока нет отзывов об этом номере</p>
                                @if($canReview)
                                    <a href="{{ route('reviews.create', ['room_id' => $room->id]) }}"
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-pencil me-2"></i>Написать первый отзыв
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Похожие номера -->
                @if($similarRooms->count() > 0)
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-house-heart text-primary me-2"></i>
                                Похожие номера
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($similarRooms as $similar)
                                    <div class="col-md-6">
                                        <div class="card border h-100">
                                            <div class="row g-0 h-100">
                                                <div class="col-4">
                                                    @if($similar->photos && count($similar->photos) > 0)
                                                        <img src="{{ asset($similar->photos[0]) }}"
                                                             alt="{{ $similar->name }}"
                                                             class="img-fluid rounded-start h-100 w-100 object-fit-cover">
                                                    @endif
                                                </div>
                                                <div class="col-8">
                                                    <div class="card-body">
                                                        <h6 class="card-title fw-bold mb-1">{{ $similar->name }}</h6>
                                                        <p class="card-text small text-muted mb-2">
                                                            <i class="bi bi-people me-1"></i>До {{ $similar->capacity }} гостей
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="h6 fw-bold text-primary mb-0">
                                                                {{ number_format($similar->price_per_night, 0, '', ' ') }} ₽
                                                            </div>
                                                            <a href="{{ route('rooms.show', $similar->id) }}"
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-arrow-right"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Боковая панель - бронирование -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white py-4">
                        <h4 class="fw-bold mb-0 text-center">
                            <i class="bi bi-calendar-check me-2"></i>
                            Бронирование
                        </h4>
                    </div>

                    <div class="card-body p-4">
                        <!-- Цена -->
                        <div class="text-center mb-4">
                            @if($room->discount)
                                <div class="text-muted text-decoration-line-through">
                                    {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                </div>
                                <div class="display-4 fw-bold text-primary mb-0">
                                    {{ number_format($room->final_price, 0, '', ' ') }} ₽
                                </div>
                                <div class="text-success small">
                                    <i class="bi bi-percent me-1"></i>Экономия {{ number_format($room->price_per_night - $room->final_price, 0, '', ' ') }} ₽
                                </div>
                            @else
                                <div class="display-4 fw-bold text-primary mb-0">
                                    {{ number_format($room->price_per_night, 0, '', ' ') }} ₽
                                </div>
                            @endif
                            <div class="text-muted">за ночь</div>
                        </div>

                        <!-- Форма бронирования -->
                        <form action="{{ route('booking.step1') }}" method="GET" id="bookingForm">
                            <input type="hidden" name="room_id" value="{{ $room->id }}">

                            <!-- Даты -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-calendar3 me-2"></i>Даты проживания
                                </label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="check_in"
                                               id="check_in" required
                                               min="{{ date('Y-m-d') }}"
                                               value="{{ request('check_in', date('Y-m-d', strtotime('+1 day'))) }}">
                                        <small class="form-text text-muted">Заезд</small>
                                    </div>
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="check_out"
                                               id="check_out" required
                                               min="{{ date('Y-m-d', strtotime('+2 days')) }}"
                                               value="{{ request('check_out', date('Y-m-d', strtotime('+3 days'))) }}">
                                        <small class="form-text text-muted">Выезд</small>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted" id="nightsCount">0 ночей</small>
                                </div>
                            </div>

                            <!-- Количество гостей -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-people me-2"></i>Количество гостей
                                </label>
                                <select class="form-select" name="guests" id="guests" required>
                                    @for($i = 1; $i <= $room->capacity; $i++)
                                        <option value="{{ $i }}"
                                            {{ request('guests', 1) == $i ? 'selected' : '' }}>
                                            {{ $i }} {{ trans_choice('гость|гостя|гостей', $i) }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <!-- Дополнительные услуги -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-plus-circle me-2"></i>Дополнительные услуги
                                </label>
                                <div class="list-group list-group-flush">
                                    @foreach($additionalServices as $service)
                                        <label class="list-group-item border-0 px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="services[]" value="{{ $service->id }}"
                                                       data-price="{{ $service->price }}">
                                                <span class="form-check-label">{{ $service->name }}</span>
                                            </div>
                                            <span class="text-primary">{{ number_format($service->price, 0, '', ' ') }} ₽</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Промокод -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-percent me-2"></i>Промокод
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="promo_code"
                                           id="promo_code" placeholder="Введите промокод">
                                    <button class="btn btn-outline-primary" type="button" id="applyPromo">
                                        Применить
                                    </button>
                                </div>
                                <div id="promoMessage" class="mt-2 small"></div>
                            </div>

                            <!-- Итоговая стоимость -->
                            <div class="card bg-light border-0 mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Стоимость номера:</span>
                                        <span id="roomPrice">0 ₽</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Доп. услуги:</span>
                                        <span id="servicesPrice">0 ₽</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2" id="discountRow" style="display: none;">
                                        <span>Скидка:</span>
                                        <span class="text-success" id="discountAmount">0 ₽</span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between fw-bold fs-5">
                                        <span>Итого:</span>
                                        <span id="totalPrice">0 ₽</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Кнопка бронирования -->
                            <button type="submit" class="btn btn-primary w-100 py-3" id="bookButton">
                                <i class="bi bi-calendar-check me-2"></i>
                                Забронировать
                            </button>

                            <!-- Уведомление о доступности -->
                            <div id="availabilityAlert" class="alert alert-warning mt-3 d-none">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span id="availabilityMessage"></span>
                            </div>
                        </form>

                        <!-- Дополнительная информация -->
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-bold mb-3">Важная информация</h6>
                            <ul class="list-unstyled small text-muted">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Бесплатная отмена до 24 часов до заезда
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Без предоплаты
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Гарантия лучшей цены
                                </li>
                                <li>
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Поддержка 24/7
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Контакты -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body text-center p-4">
                        <h6 class="fw-bold mb-3">Остались вопросы?</h6>
                        <p class="text-muted small mb-3">
                            Мы с радостью поможем с выбором номера
                        </p>
                        <div class="d-grid gap-2">
                            <a href="tel:+78005553535" class="btn btn-outline-primary">
                                <i class="bi bi-telephone me-2"></i>+7 (800) 555-35-35
                            </a>
                            <a href="{{ route('contact.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-chat-dots me-2"></i>Написать сообщение
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для дополнительных фото -->
    <div class="modal fade" id="galleryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Галерея номера</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="modalGallery">
                        @foreach($room->photos as $photo)
                            <div class="col-md-4 col-lg-3">
                                <img src="{{ asset($photo) }}"
                                     alt="{{ $room->name }}"
                                     class="img-fluid rounded shadow-sm">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .room-gallery .thumbnail {
            opacity: 0.7;
            transition: opacity 0.3s;
            border: 2px solid transparent;
        }

        .room-gallery .thumbnail:hover,
        .room-gallery .thumbnail.active {
            opacity: 1;
            border-color: #0d6efd;
        }

        .object-fit-cover {
            object-fit: cover;
        }

        .sticky-top {
            z-index: 100;
        }

        .review-item:first-child {
            border-top: none !important;
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        .response {
            border-left: 4px solid #0dcaf0;
            background-color: rgba(13, 202, 240, 0.05);
        }

        .progress-bar {
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .display-4 {
                font-size: 2.5rem;
            }

            .main-image img {
                height: 300px !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Переключение главного изображения
            window.changeMainImage = function(src, element) {
                document.getElementById('mainImage').src = src;

                // Убрать активный класс у всех миниатюр
                document.querySelectorAll('.thumbnail').forEach(thumb => {
                    thumb.classList.remove('active');
                });

                // Добавить активный класс к выбранной миниатюре
                element.classList.add('active');
            };

            // Добавление в избранное
            const favoriteBtn = document.querySelector('.favorite-btn');
            if (favoriteBtn) {
                favoriteBtn.addEventListener('click', function() {
                    const roomId = this.dataset.roomId;
                    const icon = this.querySelector('i');

                    if (!{{ auth()->check() ? 'true' : 'false' }}) {
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
                                if (data.action === 'added') {
                                    icon.classList.remove('bi-heart');
                                    icon.classList.add('bi-heart-fill');
                                    this.title = 'Удалить из избранного';
                                    showToast('Номер добавлен в избранное', 'success');
                                } else {
                                    icon.classList.remove('bi-heart-fill');
                                    icon.classList.add('bi-heart');
                                    this.title = 'Добавить в избранное';
                                    showToast('Номер удален из избранного', 'info');
                                }
                            }
                        });
                });
            }

            // Поделиться номером
            document.getElementById('shareButton')?.addEventListener('click', function() {
                if (navigator.share) {
                    navigator.share({
                        title: '{{ $room->name }}',
                        text: 'Посмотрите этот номер в отеле {{ config("app.name") }}',
                        url: window.location.href
                    });
                } else {
                    // Копировать ссыку в буфер обмена
                    navigator.clipboard.writeText(window.location.href);
                    showToast('Ссылка скопирована в буфер обмена', 'success');
                }
            });

            // Расчет стоимости
            const roomPricePerNight = {{ $room->final_price }};
            const checkIn = document.getElementById('check_in');
            const checkOut = document.getElementById('check_out');
            const guests = document.getElementById('guests');
            const nightsCount = document.getElementById('nightsCount');
            const roomPrice = document.getElementById('roomPrice');
            const servicesPrice = document.getElementById('servicesPrice');
            const totalPrice = document.getElementById('totalPrice');
            const bookButton = document.getElementById('bookButton');
            const availabilityAlert = document.getElementById('availabilityAlert');
            const availabilityMessage = document.getElementById('availabilityMessage');

            let discount = 0;
            let discountType = 'fixed'; // или 'percentage'

            function calculateTotal() {
                // Расчет количества ночей
                if (checkIn.value && checkOut.value) {
                    const start = new Date(checkIn.value);
                    const end = new Date(checkOut.value);
                    const nights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));

                    if (nights > 0) {
                        nightsCount.textContent = `${nights} ${nights === 1 ? 'ночь' : nights < 5 ? 'ночи' : 'ночей'}`;

                        // Стоимость номера
                        let roomTotal = roomPricePerNight * nights;
                        roomPrice.textContent = formatPrice(roomTotal);

                        // Стоимость дополнительных услуг
                        let servicesTotal = 0;
                        document.querySelectorAll('input[name="services[]"]:checked').forEach(checkbox => {
                            servicesTotal += parseFloat(checkbox.dataset.price);
                        });
                        servicesPrice.textContent = formatPrice(servicesTotal);

                        // Расчет скидки
                        let discountAmount = 0;
                        if (discount > 0) {
                            if (discountType === 'percentage') {
                                discountAmount = (roomTotal + servicesTotal) * (discount / 100);
                            } else {
                                discountAmount = discount;
                            }
                        }

                        // Итоговая стоимость
                        let total = roomTotal + servicesTotal - discountAmount;
                        totalPrice.textContent = formatPrice(total);

                        // Обновить скидку в UI
                        const discountRow = document.getElementById('discountRow');
                        const discountAmountEl = document.getElementById('discountAmount');

                        if (discountAmount > 0) {
                            discountRow.style.display = 'flex';
                            discountAmountEl.textContent = `-${formatPrice(discountAmount)}`;
                        } else {
                            discountRow.style.display = 'none';
                        }

                        // Проверить доступность
                        checkAvailability();
                    }
                }
            }

            // Проверка доступности номера
            function checkAvailability() {
                if (!checkIn.value || !checkOut.value) return;

                fetch('{{ route("rooms.check-specific-availability") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        room_id: {{ $room->id }},
                        check_in: checkIn.value,
                        check_out: checkOut.value,
                        guests: guests.value
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            availabilityAlert.classList.add('d-none');
                            bookButton.disabled = false;
                            bookButton.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Забронировать';
                        } else {
                            availabilityAlert.classList.remove('d-none');
                            availabilityMessage.textContent = data.message || 'На выбранные даты номер недоступен';
                            bookButton.disabled = true;
                            bookButton.innerHTML = '<i class="bi bi-x-circle me-2"></i>Недоступно';
                        }
                    });
            }

            // Форматирование цены
            function formatPrice(amount) {
                return new Intl.NumberFormat('ru-RU').format(Math.round(amount)) + ' ₽';
            }

            // Слушатели событий
            checkIn.addEventListener('change', calculateTotal);
            checkOut.addEventListener('change', calculateTotal);
            guests.addEventListener('change', calculateTotal);

            document.querySelectorAll('input[name="services[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', calculateTotal);
            });

            // Применение промокода
            document.getElementById('applyPromo')?.addEventListener('click', function() {
                const promoCode = document.getElementById('promo_code').value.trim();
                const promoMessage = document.getElementById('promoMessage');

                if (!promoCode) {
                    promoMessage.innerHTML = '<span class="text-danger">Введите промокод</span>';
                    return;
                }

                fetch('{{ route("discounts.validate") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        code: promoCode,
                        room_id: {{ $room->id }}
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.valid) {
                            discount = data.discount.value;
                            discountType = data.discount.type;
                            promoMessage.innerHTML = `<span class="text-success">${data.message}</span>`;
                        } else {
                            discount = 0;
                            promoMessage.innerHTML = `<span class="text-danger">${data.message}</span>`;
                        }
                        calculateTotal();
                    });
            });

            // Валидация дат
            function validateDates() {
                if (checkIn.value && checkOut.value) {
                    const start = new Date(checkIn.value);
                    const end = new Date(checkOut.value);

                    if (end <= start) {
                        const tomorrow = new Date(start);
                        tomorrow.setDate(tomorrow.getDate() + 1);
                        checkOut.value = tomorrow.toISOString().split('T')[0];
                    }

                    // Установить минимальную дату выезда
                    const minCheckOut = new Date(start);
                    minCheckOut.setDate(minCheckOut.getDate() + 1);
                    checkOut.min = minCheckOut.toISOString().split('T')[0];
                }
            }

            checkIn.addEventListener('change', validateDates);

            // Инициализация расчета
            calculateTotal();
            validateDates();

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

            // Загрузка отзывов через AJAX
            function loadMoreReviews(page) {
                fetch(`/rooms/{{ $room->id }}/reviews?page=${page}`)
                    .then(response => response.text())
                    .then(html => {
                        const reviewsList = document.querySelector('.reviews-list');
                        reviewsList.innerHTML += html;

                        // Обновить обработчики событий для новых элементов
                        // ...
                    });
            }

            // Бесконечная прокрутка для отзывов
            let isLoading = false;
            window.addEventListener('scroll', function() {
                const reviewsList = document.querySelector('.reviews-list');
                if (!reviewsList || isLoading) return;

                const lastReview = reviewsList.lastElementChild;
                if (!lastReview) return;

                const lastReviewOffset = lastReview.offsetTop + lastReview.clientHeight;
                const pageOffset = window.pageYOffset + window.innerHeight;

                if (pageOffset > lastReviewOffset - 100) {
                    const nextPage = parseInt(document.querySelector('.pagination .active')?.textContent || 1) + 1;
                    if (nextPage <= {{ $reviews->lastPage() }}) {
                        isLoading = true;
                        loadMoreReviews(nextPage);
                    }
                }
            });
        });
    </script>
@endpush
