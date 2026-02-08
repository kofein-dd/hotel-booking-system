@extends('layouts.app')

@section('title', 'Бронирование - Подтверждение')

@section('content')
    <div class="container py-5">
        <!-- Прогресс бар -->
        <div class="row mb-5">
            <div class="col">
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar" role="progressbar" style="width: 100%"></div>
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
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">
                            <i class="bi bi-check"></i>
                        </div>
                        <div class="mt-2 small fw-bold">Номер</div>
                    </div>
                    <div class="text-center">
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">
                            <i class="bi bi-check"></i>
                        </div>
                        <div class="mt-2 small fw-bold">Данные</div>
                    </div>
                    <div class="text-center">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">4</div>
                        <div class="mt-2 small fw-bold">Подтверждение</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Основной блок подтверждения -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-4">
                        <div class="text-center">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            <h1 class="h3 fw-bold mb-2 mt-3">
                                Проверьте детали бронирования
                            </h1>
                            <p class="text-muted mb-0">
                                Пожалуйста, проверьте все данные перед подтверждением
                            </p>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="row">
                            <!-- Левая колонка - информация о бронировании -->
                            <div class="col-lg-6">
                                <!-- Детали номера -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light border-0 py-3">
                                        <h5 class="fw-bold mb-0">
                                            <i class="bi bi-door-closed text-primary me-2"></i>
                                            Детали номера
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            @if($room->photos && count($room->photos) > 0)
                                                <img src="{{ asset($room->photos[0]) }}"
                                                     alt="{{ $room->name }}"
                                                     class="rounded me-3"
                                                     style="width: 100px; height: 75px; object-fit: cover;">
                                            @endif
                                            <div>
                                                <h6 class="fw-bold mb-1">{{ $room->name }}</h6>
                                                <p class="text-muted small mb-0">
                                                    <i class="bi bi-people me-1"></i>
                                                    До {{ $room->capacity }} гостей •
                                                    <i class="bi bi-door-closed me-1"></i>
                                                    {{ $room->size }} м²
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Характеристики -->
                                        <div class="row g-2 mb-3">
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

                                        <!-- Основные удобства -->
                                        <div class="mb-3">
                                            <h6 class="fw-bold mb-2 small">Основные удобства:</h6>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($room->amenities->take(5) as $amenity)
                                                    <span class="badge bg-light text-dark small">
                                                    <i class="bi bi-check-circle me-1"></i>{{ $amenity }}
                                                </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Даты и гости -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light border-0 py-3">
                                        <h5 class="fw-bold mb-0">
                                            <i class="bi bi-calendar-check text-primary me-2"></i>
                                            Даты и гости
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <h6 class="fw-bold mb-2 small">Даты проживания:</h6>
                                                <div class="d-flex align-items-center">
                                                    <div class="text-center me-4">
                                                        <div class="fw-bold">{{ \Carbon\Carbon::parse($bookingData['check_in'])->format('d') }}</div>
                                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($bookingData['check_in'])->translatedFormat('M') }}</div>
                                                    </div>
                                                    <div class="text-center me-4">
                                                        <i class="bi bi-arrow-right text-muted"></i>
                                                    </div>
                                                    <div class="text-center">
                                                        <div class="fw-bold">{{ \Carbon\Carbon::parse($bookingData['check_out'])->format('d') }}</div>
                                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($bookingData['check_out'])->translatedFormat('M Y') }}</div>
                                                    </div>
                                                </div>
                                                <div class="text-center mt-2">
                                                <span class="badge bg-info">
                                                    {{ $nights }} {{ trans_choice('ночь|ночи|ночей', $nights) }}
                                                </span>
                                                </div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <h6 class="fw-bold mb-2 small">Гости:</h6>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <i class="bi bi-people-fill fs-4 text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold">{{ $bookingData['adults'] }} взрослых</div>
                                                        @if($bookingData['children'] > 0)
                                                            <div class="text-muted small">{{ $bookingData['children'] }} детей</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            @if($bookingData['rooms_count'] > 1)
                                                <div class="col-12">
                                                    <h6 class="fw-bold mb-2 small">Количество номеров:</h6>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-house-door-fill text-success me-2"></i>
                                                        <span class="fw-bold">{{ $bookingData['rooms_count'] }} одинаковых номера</span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Правая колонка - информация о госте и оплата -->
                            <div class="col-lg-6">
                                <!-- Информация о госте -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light border-0 py-3">
                                        <h5 class="fw-bold mb-0">
                                            <i class="bi bi-person-badge text-primary me-2"></i>
                                            Информация о госте
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-1 small">Имя:</h6>
                                                <p class="mb-2">{{ $bookingData['first_name'] }} {{ $bookingData['last_name'] }}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-1 small">Email:</h6>
                                                <p class="mb-2">{{ $bookingData['email'] }}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-1 small">Телефон:</h6>
                                                <p class="mb-2">{{ $bookingData['phone'] }}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-1 small">Гражданство:</h6>
                                                <p class="mb-2">{{ $countries[$bookingData['nationality']] ?? $bookingData['nationality'] }}</p>
                                            </div>
                                        </div>

                                        @if($bookingData['guest2_first_name'])
                                            <div class="border-top pt-3">
                                                <h6 class="fw-bold mb-2 small">Второй гость:</h6>
                                                <p class="mb-0">{{ $bookingData['guest2_first_name'] }} {{ $bookingData['guest2_last_name'] }}</p>
                                            </div>
                                        @endif

                                        @if($bookingData['special_requests'])
                                            <div class="border-top pt-3 mt-3">
                                                <h6 class="fw-bold mb-2 small">Особые пожелания:</h6>
                                                <p class="text-muted small mb-0">{{ $bookingData['special_requests'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Дополнительные услуги -->
                                @if($selectedServices->count() > 0)
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-header bg-light border-0 py-3">
                                            <h5 class="fw-bold mb-0">
                                                <i class="bi bi-plus-circle text-success me-2"></i>
                                                Дополнительные услуги
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group list-group-flush">
                                                @foreach($selectedServices as $service)
                                                    <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between">
                                                        <div>
                                                            <small class="fw-bold">{{ $service->name }}</small>
                                                            @if($service->description)
                                                                <div class="text-muted small">{{ $service->description }}</div>
                                                            @endif
                                                        </div>
                                                        <small class="fw-bold">{{ number_format($service->price, 0, '', ' ') }} ₽</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Итоговая стоимость -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light border-0 py-3">
                                        <h5 class="fw-bold mb-0">
                                            <i class="bi bi-receipt text-primary me-2"></i>
                                            Итоговая стоимость
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between">
                                                <span>Стоимость номера:</span>
                                                <span class="fw-bold">{{ number_format($roomPrice, 0, '', ' ') }} ₽</span>
                                            </div>

                                            @if($servicesPrice > 0)
                                                <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between">
                                                    <span>Доп. услуги:</span>
                                                    <span class="fw-bold">{{ number_format($servicesPrice, 0, '', ' ') }} ₽</span>
                                                </div>
                                            @endif

                                            @if($taxAmount > 0)
                                                <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between">
                                                    <span>Налог:</span>
                                                    <span class="fw-bold">{{ number_format($taxAmount, 0, '', ' ') }} ₽</span>
                                                </div>
                                            @endif

                                            @if($discountAmount > 0)
                                                <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between text-success">
                                                    <span>Скидка:</span>
                                                    <span class="fw-bold">-{{ number_format($discountAmount, 0, '', ' ') }} ₽</span>
                                                </div>
                                            @endif

                                            <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between fw-bold fs-5">
                                                <span>Итого к оплате:</span>
                                                <span id="totalPrice">{{ number_format($totalPrice, 0, '', ' ') }} ₽</span>
                                            </div>
                                        </div>

                                        <!-- Способ оплаты -->
                                        <div class="mt-4 pt-3 border-top">
                                            <h6 class="fw-bold mb-2 small">Способ оплаты:</h6>
                                            <div class="d-flex align-items-center">
                                                @if($bookingData['payment_method'] == 'cash')
                                                    <i class="bi bi-cash text-success fs-4 me-3"></i>
                                                    <div>
                                                        <div class="fw-bold">Оплата при заселении</div>
                                                        <div class="text-muted small">Оплатите наличными или картой при заселении</div>
                                                    </div>
                                                @elseif($bookingData['payment_method'] == 'online')
                                                    <i class="bi bi-credit-card text-primary fs-4 me-3"></i>
                                                    <div>
                                                        <div class="fw-bold">Онлайн оплата</div>
                                                        <div class="text-muted small">Безопасная оплата картой онлайн</div>
                                                    </div>
                                                @else
                                                    <i class="bi bi-receipt text-warning fs-4 me-3"></i>
                                                    <div>
                                                        <div class="fw-bold">Счет для юр. лица</div>
                                                        <div class="text-muted small">Счет будет отправлен на email</div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Условия и подтверждение -->
                        <div class="card border-0 shadow-sm mt-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="fw-bold mb-2">Условия бронирования</h6>
                                        <p class="text-muted small mb-0">
                                            Нажимая "Подтвердить бронирование", вы соглашаетесь с
                                            <a href="{{ route('pages.show', 'terms') }}" target="_blank" class="text-decoration-none">
                                                условиями бронирования
                                            </a>
                                            и
                                            <a href="{{ route('pages.show', 'privacy') }}" target="_blank" class="text-decoration-none">
                                                политикой конфиденциальности
                                            </a>.
                                        </p>

                                        <!-- Уведомления -->
                                        <div class="mt-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       id="confirmation_newsletter" name="confirmation_newsletter" checked>
                                                <label class="form-check-label small" for="confirmation_newsletter">
                                                    Получать новости и специальные предложения
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 text-md-end">
                                        <form action="{{ route('booking.confirm') }}" method="POST" id="confirmForm">
                                            @csrf

                                            <!-- Скрытые поля -->
                                            @foreach($bookingData as $key => $value)
                                                @if(is_array($value))
                                                    @foreach($value as $item)
                                                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                                    @endforeach
                                                @else
                                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                                @endif
                                            @endforeach

                                            <input type="hidden" name="room_id" value="{{ $room->id }}">
                                            <input type="hidden" name="total_price" value="{{ $totalPrice }}">

                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-success btn-lg py-3" id="confirmButton">
                                                    <i class="bi bi-check-circle me-2"></i>
                                                    Подтвердить бронирование
                                                </button>
                                                <a href="{{ route('booking.step3') . '?' . http_build_query(request()->except(['_token'])) }}"
                                                   class="btn btn-outline-secondary">
                                                    <i class="bi bi-pencil me-2"></i>Редактировать данные
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Важная информация -->
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-calendar-x text-primary fs-1 mb-3"></i>
                                <h6 class="fw-bold mb-2">Отмена бронирования</h6>
                                <p class="text-muted small mb-0">
                                    Бесплатная отмена до 24 часов до заезда
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-shield-check text-success fs-1 mb-3"></i>
                                <h6 class="fw-bold mb-2">Безопасная оплата</h6>
                                <p class="text-muted small mb-0">
                                    SSL-шифрование, безопасные платежи
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-headset text-info fs-1 mb-3"></i>
                                <h6 class="fw-bold mb-2">Поддержка 24/7</h6>
                                <p class="text-muted small mb-0">
                                    Круглосуточная помощь по любым вопросам
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Частые вопросы</h5>
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item border-0 mb-2">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faq1">
                                        Когда придет подтверждение бронирования?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse"
                                     data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Подтверждение придет в течение 2 часов на указанный email.
                                        В редких случаях это может занять до 24 часов.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0 mb-2">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faq2">
                                        Что нужно иметь при заселении?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse"
                                     data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Паспорт или документ, удостоверяющий личность.
                                        Для иностранных граждан - действующий паспорт с визой (если требуется).
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faq3">
                                        Можно ли изменить даты после бронирования?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse"
                                     data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Да, изменения возможны при наличии свободных номеров.
                                        Свяжитесь с нами по телефону или через личный кабинет.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно подтверждения -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i>
                        Подтверждение бронирования
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-hourglass-split text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Обработка вашего запроса</h5>
                    <p class="text-muted mb-0">
                        Пожалуйста, не закрывайте страницу. Идет обработка вашего бронирования...
                    </p>
                    <div class="progress mt-4">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="processingProgress" role="progressbar" style="width: 0%"></div>
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

        .card {
            border-radius: 12px;
        }

        .accordion-button {
            border-radius: 8px !important;
            font-weight: 500;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0d6efd;
        }

        .badge.bg-info {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .list-group-item {
            border-left: none;
            border-right: none;
        }

        .list-group-item:first-child {
            border-top: none;
            padding-top: 0;
        }

        .list-group-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
            transition: all 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        }

        @media (max-width: 768px) {
            .text-md-end {
                text-align: left !important;
            }

            .d-grid {
                margin-top: 1rem;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Подтверждение бронирования
            const confirmForm = document.getElementById('confirmForm');
            const confirmButton = document.getElementById('confirmButton');
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            const processingProgress = document.getElementById('processingProgress');

            if (confirmForm && confirmButton) {
                let isSubmitting = false;

                confirmForm.addEventListener('submit', function(e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return;
                    }

                    // Показать модальное окно
                    confirmationModal.show();

                    // Запустить анимацию прогресса
                    let progress = 0;
                    const progressInterval = setInterval(() => {
                        progress += 5;
                        processingProgress.style.width = progress + '%';

                        if (progress >= 100) {
                            clearInterval(progressInterval);
                        }
                    }, 200);

                    // Блокировать кнопку
                    isSubmitting = true;
                    confirmButton.disabled = true;
                    confirmButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Обработка...';

                    // Продолжить отправку формы
                    return true;
                });
            }

            // Сохранение данных в localStorage на случай сбоя
            function saveBookingData() {
                const bookingData = {
                    room: {
                        id: {{ $room->id }},
                        name: '{{ $room->name }}',
                        price: {{ $totalPrice }}
                    },
                    dates: {
                        check_in: '{{ $bookingData["check_in"] }}',
                        check_out: '{{ $bookingData["check_out"] }}',
                        nights: {{ $nights }}
                    },
                    guest: {
                        name: '{{ $bookingData["first_name"] }} {{ $bookingData["last_name"] }}',
                        email: '{{ $bookingData["email"] }}',
                        phone: '{{ $bookingData["phone"] }}'
                    },
                    total_price: {{ $totalPrice }},
                    timestamp: new Date().getTime()
                };

                localStorage.setItem('lastBookingAttempt', JSON.stringify(bookingData));
            }

            // Сохранить данные при загрузке страницы
            saveBookingData();

            // Проверка восстановления бронирования
            const lastBooking = localStorage.getItem('lastBookingAttempt');
            if (lastBooking) {
                const booking = JSON.parse(lastBooking);
                const oneHourAgo = new Date().getTime() - (60 * 60 * 1000);

                if (booking.timestamp > oneHourAgo && booking.room.id === {{ $room->id }}) {
                    // Показать уведомление о восстановлении
                    const recoveryAlert = document.createElement('div');
                    recoveryAlert.className = 'alert alert-info alert-dismissible fade show';
                    recoveryAlert.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-arrow-clockwise me-3 fs-4"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Восстановление бронирования</h6>
                            <p class="mb-0">Мы обнаружили незавершенное бронирование этого номера. Данные были восстановлены.</p>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                `;

                    document.querySelector('.card-body').prepend(recoveryAlert);
                }
            }

            // Печать подтверждения
            const printButton = document.createElement('button');
            printButton.type = 'button';
            printButton.className = 'btn btn-outline-primary btn-sm mt-3';
            printButton.innerHTML = '<i class="bi bi-printer me-2"></i>Распечатать подтверждение';
            printButton.addEventListener('click', function() {
                window.print();
            });

            document.querySelector('.card-header.bg-white')?.appendChild(printButton);

            // Таймер на подтверждение
            let confirmationTimer = 15 * 60; // 15 минут
            const timerElement = document.createElement('div');
            timerElement.className = 'alert alert-warning mt-3';
            timerElement.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-clock-history me-3 fs-4"></i>
                <div>
                    <h6 class="alert-heading mb-1">Внимание!</h6>
                    <p class="mb-0">Бронирование будет сохранено на <span id="timerMinutes">${Math.floor(confirmationTimer / 60)}</span> минут. Осталось: <span id="timerCount">${confirmationTimer}</span> секунд</p>
                </div>
            </div>
        `;

            document.querySelector('.card-body')?.prepend(timerElement);

            const timerInterval = setInterval(() => {
                confirmationTimer--;
                document.getElementById('timerCount').textContent = confirmationTimer;
                document.getElementById('timerMinutes').textContent = Math.floor(confirmationTimer / 60);

                if (confirmationTimer <= 60) {
                    timerElement.className = 'alert alert-danger mt-3';
                }

                if (confirmationTimer <= 0) {
                    clearInterval(timerInterval);
                    timerElement.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle me-3 fs-4"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Время истекло!</h6>
                            <p class="mb-0">Время на подтверждение бронирования истекло. Пожалуйста, начните заново.</p>
                        </div>
                    </div>
                `;

                    // Деактивировать кнопку подтверждения
                    if (confirmButton) {
                        confirmButton.disabled = true;
                        confirmButton.innerHTML = '<i class="bi bi-clock me-2"></i>Время истекло';
                    }
                }
            }, 1000);

            // Отслеживание ухода со страницы
            let pageUnloadWarning = false;

            window.addEventListener('beforeunload', function(e) {
                if (!isSubmitting && !pageUnloadWarning) {
                    const message = 'Вы уверены, что хотите покинуть страницу? Незавершенное бронирование будет сохранено.';
                    e.returnValue = message;
                    return message;
                }
            });

            // Сохранение при уходе
            window.addEventListener('unload', function() {
                if (!isSubmitting) {
                    // Отправить запрос на сохранение черновика
                    navigator.sendBeacon('{{ route("booking.save-draft") }}',
                        new FormData(confirmForm));
                }
            });

            // Поделиться бронированием
            const shareButton = document.createElement('button');
            shareButton.type = 'button';
            shareButton.className = 'btn btn-outline-info btn-sm mt-3 ms-2';
            shareButton.innerHTML = '<i class="bi bi-share me-2"></i>Поделиться';
            shareButton.addEventListener('click', function() {
                if (navigator.share) {
                    navigator.share({
                        title: 'Мое бронирование в {{ config("app.name") }}',
                        text: `Я забронировал(а) номер "${ $room->name }" на {{ \Carbon\Carbon::parse($bookingData["check_in"])->translatedFormat("d F") }} - {{ \Carbon\Carbon::parse($bookingData["check_out"])->translatedFormat("d F Y") }}`,
                        url: window.location.href
                    });
                } else {
                    // Копировать ссылку в буфер обмена
                    navigator.clipboard.writeText(window.location.href);
                    alert('Ссылка скопирована в буфер обмена');
                }
            });

            document.querySelector('.card-header.bg-white')?.appendChild(shareButton);

            // Плавающая кнопка подтверждения для мобильных
            if (window.innerWidth < 768) {
                const floatingButton = document.createElement('div');
                floatingButton.className = 'fixed-bottom p-3 bg-white shadow-lg border-top';
                floatingButton.innerHTML = `
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="fw-bold">{{ number_format($totalPrice, 0, '', ' ') }} ₽</div>
                            <div class="small text-muted">Итого</div>
                        </div>
                        <div class="col-auto">
                            <button type="submit" form="confirmForm" class="btn btn-success">
                                <i class="bi bi-check-circle me-2"></i>Подтвердить
                            </button>
                        </div>
                    </div>
                </div>
            `;

                document.body.appendChild(floatingButton);

                // Скрыть при прокрутке вниз
                let lastScrollTop = 0;
                window.addEventListener('scroll', function() {
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

                    if (scrollTop > lastScrollTop) {
                        // Прокрутка вниз - скрыть
                        floatingButton.style.transform = 'translateY(100%)';
                    } else {
                        // Прокрутка вверх - показать
                        floatingButton.style.transform = 'translateY(0)';
                    }

                    lastScrollTop = scrollTop;
                });
            }
        });
    </script>
@endpush
