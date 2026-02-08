@extends('layouts.app')

@section('title', 'Бронирование - Выбор номера')

@section('content')
    <div class="container py-5">
        <!-- Прогресс бар -->
        <div class="row mb-5">
            <div class="col">
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar" role="progressbar" style="width: 50%"></div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <div class="text-center">
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">
                            <i class="bi bi-check"></i>
                        </div>
                        <div class="mt-2 small fw-bold">Даты</div>
                    </div>
                    <div class="text-center">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">2</div>
                        <div class="mt-2 small fw-bold">Номер</div>
                    </div>
                    <div class="text-center">
                        <div class="step-number bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">3</div>
                        <div class="mt-2 small text-muted">Данные</div>
                    </div>
                    <div class="text-center">
                        <div class="step-number bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">4</div>
                        <div class="mt-2 small text-muted">Подтверждение</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Левая панель - фильтры -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-funnel text-primary me-2"></i>
                            Фильтры
                        </h5>
                    </div>

                    <div class="card-body">
                        <!-- Выбранные даты -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Ваши даты:</h6>
                            <div class="alert alert-info py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="fw-bold">{{ \Carbon\Carbon::parse(request('check_in'))->translatedFormat('d F') }}</small><br>
                                        <small>заезд</small>
                                    </div>
                                    <div class="text-center">
                                        <i class="bi bi-arrow-right"></i><br>
                                        <small>{{ $nights }} {{ trans_choice('ночь|ночи|ночей', $nights) }}</small>
                                    </div>
                                    <div class="text-end">
                                        <small class="fw-bold">{{ \Carbon\Carbon::parse(request('check_out'))->translatedFormat('d F') }}</small><br>
                                        <small>выезд</small>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('booking.step1') }}" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-pencil me-1"></i>Изменить
                            </a>
                        </div>

                        <!-- Количество гостей -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Гости:</h6>
                            <div class="alert alert-light py-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <small class="fw-bold">{{ request('adults', 1) }} взр.</small><br>
                                        @if(request('children', 0) > 0)
                                            <small class="fw-bold">{{ request('children') }} дет.</small><br>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        <small class="fw-bold">{{ request('adults', 1) + request('children', 0) }}</small><br>
                                        <small>всего</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Тип номера -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Тип номера</h6>
                            <div class="list-group list-group-flush">
                                @foreach($roomTypes as $type => $count)
                                    <label class="list-group-item border-0 px-0 py-2 d-flex justify-content-between align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input room-type-filter" type="checkbox"
                                                   value="{{ $type }}"
                                                   id="type_{{ $loop->index }}"
                                                {{ in_array($type, (array)request('room_types', [])) ? 'checked' : '' }}>
                                            <span class="form-check-label">{{ $type }}</span>
                                        </div>
                                        <span class="badge bg-secondary">{{ $count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Цена -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Цена за {{ $nights }} {{ trans_choice('ночь|ночи|ночей', $nights) }}</h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>от</small>
                                    <small id="priceRangeValue">{{ request('price_min', 0) }} - {{ request('price_max', $maxPrice) }} ₽</small>
                                </div>
                                <input type="range" class="form-range" min="0" max="{{ $maxPrice }}"
                                       step="1000" value="{{ request('price_max', $maxPrice) }}"
                                       id="priceSlider">
                            </div>
                        </div>

                        <!-- Удобства -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Основные удобства</h6>
                            <div class="list-group list-group-flush">
                                @foreach($mainAmenities as $amenity)
                                    <label class="list-group-item border-0 px-0 py-2 d-flex align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input amenity-filter" type="checkbox"
                                                   value="{{ $amenity }}"
                                                   id="amenity_{{ $loop->index }}">
                                            <span class="form-check-label">{{ $amenity }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
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
                </div>
            </div>

            <!-- Основной контент - список номеров -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 fw-bold mb-2">
                                    <i class="bi bi-door-closed text-primary me-2"></i>
                                    Выберите номер
                                </h1>
                                <p class="text-muted mb-0">
                                    {{ $rooms->total() }} {{ trans_choice('номер доступен|номера доступны|номеров доступно', $rooms->total()) }}
                                    на выбранные даты
                                </p>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-sort-down me-2"></i>
                                    {{ request('sort') == 'price_asc' ? 'Сначала дешевле' :
                                       request('sort') == 'price_desc' ? 'Сначала дороже' :
                                       request('sort') == 'rating_desc' ? 'По рейтингу' : 'По умолчанию' }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="#" data-sort="default">
                                            По умолчанию
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" data-sort="price_asc">
                                            Сначала дешевле
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" data-sort="price_desc">
                                            Сначала дороже
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" data-sort="rating_desc">
                                            По рейтингу
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        @if($rooms->count() > 0)
                            <!-- Список номеров -->
                            <div class="row g-4" id="roomsList">
                                @foreach($rooms as $room)
                                    <div class="col-12">
                                        <div class="card room-card border-0 shadow-sm">
                                            <div class="row g-0">
                                                <!-- Изображение -->
                                                <div class="col-md-4">
                                                    @if($room->photos && count($room->photos) > 0)
                                                        <img src="{{ asset($room->photos[0]) }}"
                                                             alt="{{ $room->name }}"
                                                             class="img-fluid rounded-start h-100 w-100 object-fit-cover"
                                                             style="min-height: 200px;">
                                                    @else
                                                        <div class="bg-secondary h-100 w-100 d-flex align-items-center justify-content-center">
                                                            <i class="bi bi-image text-white" style="font-size: 2rem;"></i>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Информация -->
                                                <div class="col-md-5">
                                                    <div class="card-body h-100 d-flex flex-column">
                                                        <!-- Заголовок и рейтинг -->
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <h5 class="card-title fw-bold mb-1">{{ $room->name }}</h5>
                                                                <div class="d-flex align-items-center gap-2">
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
                                                                    <span class="text-muted small">
                                                                    ({{ $room->reviews_count }})
                                                                </span>
                                                                </div>
                                                            </div>

                                                            <!-- Бейджи -->
                                                            <div class="text-end">
                                                                @if($room->discount)
                                                                    <span class="badge bg-danger">
                                                                    -{{ $room->discount->value }}%
                                                                </span>
                                                                @endif
                                                                @if($room->is_popular)
                                                                    <span class="badge bg-warning text-dark mt-1">
                                                                    Популярный
                                                                </span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <!-- Описание -->
                                                        <p class="card-text text-muted small mb-3 flex-grow-1">
                                                            {{ Str::limit($room->description, 150) }}
                                                        </p>

                                                        <!-- Характеристики -->
                                                        <div class="room-features mb-3">
                                                            <div class="row g-2">
                                                                <div class="col-6">
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-people me-1"></i>
                                                                        До {{ $room->capacity }} гостей
                                                                    </small>
                                                                </div>
                                                                <div class="col-6">
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-door-closed me-1"></i>
                                                                        {{ $room->size }} м²
                                                                    </small>
                                                                </div>
                                                                <div class="col-6">
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-bed me-1"></i>
                                                                        {{ $room->bed_type }}
                                                                    </small>
                                                                </div>
                                                                <div class="col-6">
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-window me-1"></i>
                                                                        {{ $room->view }}
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Удобства -->
                                                        <div class="room-amenities">
                                                            @foreach($room->amenities->take(3) as $amenity)
                                                                <span class="badge bg-light text-dark me-1 mb-1 small">
                                                                <i class="bi bi-check-circle me-1"></i>{{ $amenity }}
                                                            </span>
                                                            @endforeach
                                                            @if(count($room->amenities) > 3)
                                                                <span class="badge bg-light text-dark small">
                                                                +{{ count($room->amenities) - 3 }}
                                                            </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Цена и кнопка -->
                                                <div class="col-md-3">
                                                    <div class="card-body h-100 d-flex flex-column justify-content-between border-start">
                                                        <!-- Цена -->
                                                        <div class="text-end mb-3">
                                                            @if($room->discount)
                                                                <div class="text-muted text-decoration-line-through small">
                                                                    {{ number_format($room->price_per_night * $nights, 0, '', ' ') }} ₽
                                                                </div>
                                                            @endif
                                                            <div class="h4 fw-bold text-primary mb-0">
                                                                {{ number_format($room->final_price * $nights, 0, '', ' ') }} ₽
                                                            </div>
                                                            <div class="text-muted small">
                                                                за {{ $nights }} {{ trans_choice('ночь|ночи|ночей', $nights) }}
                                                            </div>
                                                            <div class="text-success small">
                                                                {{ number_format($room->final_price, 0, '', ' ') }} ₽ / ночь
                                                            </div>
                                                        </div>

                                                        <!-- Кнопки действий -->
                                                        <div class="d-grid gap-2">
                                                            <a href="{{ route('rooms.show', $room->id) }}"
                                                               target="_blank"
                                                               class="btn btn-outline-primary btn-sm">
                                                                <i class="bi bi-eye me-1"></i>Подробнее
                                                            </a>

                                                            <form action="{{ route('booking.step3') }}" method="GET" class="d-inline">
                                                                <input type="hidden" name="room_id" value="{{ $room->id }}">
                                                                <input type="hidden" name="check_in" value="{{ request('check_in') }}">
                                                                <input type="hidden" name="check_out" value="{{ request('check_out') }}">
                                                                <input type="hidden" name="adults" value="{{ request('adults', 1) }}">
                                                                <input type="hidden" name="children" value="{{ request('children', 0) }}">
                                                                <input type="hidden" name="rooms_count" value="{{ request('rooms_count', 1) }}">

                                                                @if(request()->has('promo_code'))
                                                                    <input type="hidden" name="promo_code" value="{{ request('promo_code') }}">
                                                                @endif

                                                                <button type="submit" class="btn btn-primary w-100">
                                                                    <i class="bi bi-check-circle me-1"></i>Выбрать
                                                                </button>
                                                            </form>
                                                        </div>

                                                        <!-- Уведомление о доступности -->
                                                        @if($room->available_rooms < request('rooms_count', 1))
                                                            <div class="alert alert-warning small mt-2 p-2 mb-0">
                                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                                Осталось {{ $room->available_rooms }} {{ trans_choice('номер|номера|номеров', $room->available_rooms) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Пагинация -->
                            @if($rooms->hasPages())
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="small text-muted">
                                        Показано {{ $rooms->firstItem() }}-{{ $rooms->lastItem() }} из {{ $rooms->total() }}
                                    </div>
                                    <nav aria-label="Навигация по страницам">
                                        {{ $rooms->withQueryString()->links() }}
                                    </nav>
                                </div>
                            @endif
                        @else
                            <!-- Сообщение об отсутствии номеров -->
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="bi bi-door-closed text-muted" style="font-size: 4rem;"></i>
                                </div>
                                <h4 class="fw-bold mb-3">Нет доступных номеров</h4>
                                <p class="text-muted mb-4">
                                    На выбранные даты нет доступных номеров, соответствующих вашим критериям.
                                </p>
                                <div class="d-flex justify-content-center gap-3">
                                    <a href="{{ route('booking.step1') }}" class="btn btn-outline-primary">
                                        <i class="bi bi-calendar-date me-2"></i>Изменить даты
                                    </a>
                                    <button type="button" class="btn btn-primary" id="resetAllFilters">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Сбросить фильтры
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Дополнительная информация -->
                <div class="row mt-4 g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">
                                    <i class="bi bi-info-circle text-info me-2"></i>
                                    Что включено в стоимость?
                                </h6>
                                <ul class="list-unstyled small text-muted">
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        Проживание в номере
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        Бесплатный Wi-Fi
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        Завтрак (шведский стол)
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        Доступ в бассейн
                                    </li>
                                    <li>
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        Парковка
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">
                                    <i class="bi bi-question-circle text-warning me-2"></i>
                                    Нужна помощь с выбором?
                                </h6>
                                <p class="text-muted small mb-3">
                                    Наши специалисты помогут подобрать идеальный номер для вашего отдыха
                                </p>
                                <div class="d-grid gap-2">
                                    <a href="tel:+78005553535" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-telephone me-2"></i>Позвонить
                                    </a>
                                    <a href="{{ route('contact.index') }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-chat-dots me-2"></i>Написать сообщение
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно сравнения номеров -->
    <div class="modal fade" id="compareModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Сравнение номеров</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="compareContent">
                        <!-- Контент будет загружен через AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .step-number {
            transition: all 0.3s;
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

        .object-fit-cover {
            object-fit: cover;
        }

        .rating {
            color: #ffc107;
        }

        .room-amenities .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }

        .sticky-top {
            z-index: 100;
        }

        .border-start {
            border-left: 1px solid #dee2e6 !important;
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
            .room-card .row {
                flex-direction: column;
            }

            .room-card .col-md-4,
            .room-card .col-md-5,
            .room-card .col-md-3 {
                width: 100%;
            }

            .border-start {
                border-left: none !important;
                border-top: 1px solid #dee2e6 !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Слайдер цены
            const priceSlider = document.getElementById('priceSlider');
            const priceRangeValue = document.getElementById('priceRangeValue');
            const maxPrice = {{ $maxPrice }};

            if (priceSlider) {
                priceSlider.addEventListener('input', function() {
                    const value = this.value;
                    priceRangeValue.textContent = `0 - ${new Intl.NumberFormat('ru-RU').format(value)} ₽`;
                });
            }

            // Применение фильтров
            document.getElementById('applyFilters')?.addEventListener('click', function() {
                const params = new URLSearchParams(window.location.search);

                // Собрать типы номеров
                const selectedTypes = [];
                document.querySelectorAll('.room-type-filter:checked').forEach(checkbox => {
                    selectedTypes.push(checkbox.value);
                });

                if (selectedTypes.length > 0) {
                    params.set('room_types', selectedTypes.join(','));
                } else {
                    params.delete('room_types');
                }

                // Собрать удобства
                const selectedAmenities = [];
                document.querySelectorAll('.amenity-filter:checked').forEach(checkbox => {
                    selectedAmenities.push(checkbox.value);
                });

                if (selectedAmenities.length > 0) {
                    params.set('amenities', selectedAmenities.join(','));
                } else {
                    params.delete('amenities');
                }

                // Цена
                params.set('price_max', priceSlider.value);

                // Перезагрузить страницу с новыми параметрами
                window.location.href = '{{ route("booking.step2") }}?' + params.toString();
            });

            // Сброс фильтров
            document.getElementById('resetFilters')?.addEventListener('click', function() {
                const params = new URLSearchParams(window.location.search);

                // Удалить параметры фильтрации
                params.delete('room_types');
                params.delete('amenities');
                params.delete('price_max');
                params.delete('sort');

                window.location.href = '{{ route("booking.step2") }}?' + params.toString();
            });

            document.getElementById('resetAllFilters')?.addEventListener('click', function() {
                window.location.href = '{{ route("booking.step2") }}?' +
                    'check_in={{ request("check_in") }}&' +
                    'check_out={{ request("check_out") }}&' +
                    'adults={{ request("adults", 1) }}&' +
                    'children={{ request("children", 0) }}&' +
                    'rooms_count={{ request("rooms_count", 1) }}';
            });

            // Сортировка
            document.querySelectorAll('[data-sort]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sort = this.dataset.sort;
                    const params = new URLSearchParams(window.location.search);
                    params.set('sort', sort);
                    window.location.href = '{{ route("booking.step2") }}?' + params.toString();
                });
            });

            // Сравнение номеров
            const compareRooms = new Set();

            // Функция добавления/удаления из сравнения
            window.toggleCompare = function(roomId, button) {
                if (compareRooms.has(roomId)) {
                    compareRooms.delete(roomId);
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-outline-primary');
                    button.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Сравнить';
                } else {
                    compareRooms.add(roomId);
                    button.classList.remove('btn-outline-primary');
                    button.classList.add('btn-primary');
                    button.innerHTML = '<i class="bi bi-check-circle me-1"></i>В сравнении';

                    // Ограничить количество номеров для сравнения
                    if (compareRooms.size > 3) {
                        const firstRoomId = Array.from(compareRooms)[0];
                        compareRooms.delete(firstRoomId);
                        const firstButton = document.querySelector(`[data-room-id="${firstRoomId}"]`);
                        if (firstButton) {
                            firstButton.classList.remove('btn-primary');
                            firstButton.classList.add('btn-outline-primary');
                            firstButton.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Сравнить';
                        }
                    }
                }

                // Обновить кнопку сравнения в шапке
                updateCompareButton();
            };

            // Обновление кнопки сравнения
            function updateCompareButton() {
                const compareButton = document.getElementById('compareButton');
                if (compareButton) {
                    if (compareRooms.size > 0) {
                        compareButton.classList.remove('d-none');
                        compareButton.innerHTML = `
                        <i class="bi bi-bar-chart me-2"></i>
                        Сравнить (${compareRooms.size})
                    `;
                    } else {
                        compareButton.classList.add('d-none');
                    }
                }
            }

            // Загрузка сравнения
            document.getElementById('compareButton')?.addEventListener('click', function() {
                if (compareRooms.size < 2) {
                    alert('Выберите хотя бы 2 номера для сравнения');
                    return;
                }

                const roomIds = Array.from(compareRooms);

                fetch('{{ route("rooms.compare") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ room_ids: roomIds })
                })
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('compareContent').innerHTML = html;
                        const modal = new bootstrap.Modal(document.getElementById('compareModal'));
                        modal.show();
                    });
            });

            // Быстрый просмотр номера
            document.querySelectorAll('.room-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Проверить, не был ли клик по кнопке или ссылке
                    if (e.target.closest('a, button, form')) {
                        return;
                    }

                    const roomId = this.querySelector('form input[name="room_id"]')?.value;
                    if (roomId) {
                        // Открыть страницу номера в новой вкладке
                        window.open(`/rooms/${roomId}`, '_blank');
                    }
                });
            });

            // Автоматическое обновление доступности
            function checkAvailability() {
                const roomIds = [];
                document.querySelectorAll('form input[name="room_id"]').forEach(input => {
                    roomIds.push(input.value);
                });

                if (roomIds.length === 0) return;

                fetch('{{ route("booking.check-availability") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        room_ids: roomIds,
                        check_in: '{{ request("check_in") }}',
                        check_out: '{{ request("check_out") }}',
                        rooms_count: '{{ request("rooms_count", 1) }}'
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(room => {
                            const roomElement = document.querySelector(`form input[name="room_id"][value="${room.id}"]`)?.closest('.room-card');
                            if (roomElement) {
                                const bookButton = roomElement.querySelector('.btn-primary');
                                const alertDiv = roomElement.querySelector('.alert-warning');

                                if (!room.available) {
                                    if (bookButton) {
                                        bookButton.disabled = true;
                                        bookButton.innerHTML = '<i class="bi bi-x-circle me-1"></i>Недоступно';
                                        bookButton.className = 'btn btn-secondary w-100';
                                    }

                                    if (!alertDiv) {
                                        const warningDiv = document.createElement('div');
                                        warningDiv.className = 'alert alert-danger small mt-2 p-2 mb-0';
                                        warningDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Номер занят на выбранные даты';
                                        roomElement.querySelector('.card-body').appendChild(warningDiv);
                                    }
                                } else if (room.available_rooms < {{ request('rooms_count', 1) }}) {
                                    if (alertDiv) {
                                        alertDiv.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Осталось ${room.available_rooms} ${room.available_rooms === 1 ? 'номер' : room.available_rooms < 5 ? 'номера' : 'номеров'}`;
                                    }
                                }
                            }
                        });
                    });
            }

            // Проверять доступность каждые 30 секунд
            setInterval(checkAvailability, 30000);

            // Предварительная загрузка изображений для лучшего UX
            function preloadImages() {
                const images = [];
                document.querySelectorAll('.room-card img').forEach(img => {
                    const src = img.src;
                    if (src && !images.includes(src)) {
                        images.push(src);
                        const image = new Image();
                        image.src = src;
                    }
                });
            }

            // Запустить предзагрузку после загрузки страницы
            setTimeout(preloadImages, 1000);

            // Сохранение выбранного номера в localStorage для восстановления
            document.querySelectorAll('form[action="{{ route("booking.step3") }}"] button[type="submit"]').forEach(button => {
                button.addEventListener('click', function() {
                    const form = this.closest('form');
                    const roomId = form.querySelector('input[name="room_id"]').value;

                    // Сохранить выбранный номер
                    localStorage.setItem('selectedRoom', roomId);
                    localStorage.setItem('bookingDates', JSON.stringify({
                        check_in: '{{ request("check_in") }}',
                        check_out: '{{ request("check_out") }}',
                        adults: '{{ request("adults", 1) }}',
                        children: '{{ request("children", 0) }}',
                        rooms_count: '{{ request("rooms_count", 1) }}'
                    }));
                });
            });

            // Восстановление из localStorage
            const selectedRoom = localStorage.getItem('selectedRoom');
            const bookingDates = JSON.parse(localStorage.getItem('bookingDates') || '{}');

            if (selectedRoom && bookingDates.check_in === '{{ request("check_in") }}') {
                // Подсветить выбранный номер
                const selectedCard = document.querySelector(`form input[name="room_id"][value="${selectedRoom}"]`)?.closest('.room-card');
                if (selectedCard) {
                    selectedCard.style.boxShadow = '0 0 0 3px rgba(13, 110, 253, 0.5)';
                    selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
@endpush
