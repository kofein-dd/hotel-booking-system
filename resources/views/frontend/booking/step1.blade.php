@extends('layouts.app')

@section('title', 'Бронирование - Выбор дат')

@section('content')
    <div class="container py-5">
        <!-- Прогресс бар -->
        <div class="row mb-5">
            <div class="col">
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%"></div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <div class="text-center">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">1</div>
                        <div class="mt-2 small fw-bold">Даты</div>
                    </div>
                    <div class="text-center">
                        <div class="step-number bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">2</div>
                        <div class="mt-2 small text-muted">Номер</div>
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

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-4">
                        <h1 class="h3 fw-bold mb-2 text-center">
                            <i class="bi bi-calendar3 text-primary me-2"></i>
                            Выберите даты проживания
                        </h1>
                        <p class="text-muted text-center mb-0">
                            Укажите период, на который хотите забронировать номер
                        </p>
                    </div>

                    <div class="card-body p-4">
                        <form action="{{ route('booking.step2') }}" method="GET" id="bookingStep1Form">
                            @if(request('room_id'))
                                <input type="hidden" name="room_id" value="{{ request('room_id') }}">
                            @endif

                            <div class="row">
                                <!-- Календарь -->
                                <div class="col-md-6 mb-4">
                                    <div id="calendar" class="booking-calendar"></div>

                                    <!-- Быстрый выбор -->
                                    <div class="mt-3">
                                        <label class="form-label small fw-bold">Быстрый выбор:</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-days="2">2 ночи</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-days="3">3 ночи</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-days="7">Неделя</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-days="14">2 недели</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Форма выбора дат -->
                                <div class="col-md-6">
                                    <!-- Даты -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-calendar-date me-2"></i>Даты проживания
                                        </label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-calendar-plus"></i>
                                                </span>
                                                    <input type="text" class="form-control datepicker"
                                                           name="check_in" id="check_in" required
                                                           placeholder="Заезд"
                                                           value="{{ old('check_in', request('check_in', date('d.m.Y', strtotime('+1 day')))) }}">
                                                </div>
                                                <small class="form-text text-muted">Дата заезда</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-calendar-minus"></i>
                                                </span>
                                                    <input type="text" class="form-control datepicker"
                                                           name="check_out" id="check_out" required
                                                           placeholder="Выезд"
                                                           value="{{ old('check_out', request('check_out', date('d.m.Y', strtotime('+3 days')))) }}">
                                                </div>
                                                <small class="form-text text-muted">Дата выезда</small>
                                            </div>
                                        </div>
                                        <div class="text-center mt-2">
                                            <div class="badge bg-info text-dark" id="nightsCount">
                                                0 ночей
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Количество гостей -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-people me-2"></i>Количество гостей
                                        </label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-person"></i>
                                                </span>
                                                    <input type="number" class="form-control"
                                                           name="adults" id="adults"
                                                           min="1" max="10" value="{{ old('adults', request('adults', 1)) }}"
                                                           required>
                                                </div>
                                                <small class="form-text text-muted">Взрослые</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-person-heart"></i>
                                                </span>
                                                    <input type="number" class="form-control"
                                                           name="children" id="children"
                                                           min="0" max="10" value="{{ old('children', request('children', 0)) }}">
                                                </div>
                                                <small class="form-text text-muted">Дети</small>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted" id="totalGuests">
                                                Всего гостей: 1
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Количество номеров -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-door-closed me-2"></i>Количество номеров
                                        </label>
                                        <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-house"></i>
                                        </span>
                                            <input type="number" class="form-control"
                                                   name="rooms_count" id="rooms_count"
                                                   min="1" max="10" value="{{ old('rooms_count', request('rooms_count', 1)) }}"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="document.getElementById('rooms_count').stepUp()">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="document.getElementById('rooms_count').stepDown()">
                                                <i class="bi bi-dash"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Количество одинаковых номеров для бронирования
                                        </small>
                                    </div>

                                    <!-- Праздничные дни -->
                                    <div class="alert alert-info mb-4">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                                            <div>
                                                <h6 class="alert-heading mb-1">Особые периоды</h6>
                                                <p class="mb-0 small">
                                                    В праздничные дни цены могут отличаться.
                                                    <a href="#" class="text-decoration-none" data-bs-toggle="modal"
                                                       data-bs-target="#holidaysModal">
                                                        Посмотреть календарь
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Кнопки -->
                                    <div class="d-grid gap-3">
                                        <button type="submit" class="btn btn-primary btn-lg py-3" id="continueButton">
                                            <i class="bi bi-arrow-right me-2"></i>
                                            Продолжить
                                        </button>
                                        <a href="{{ route('rooms.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left me-2"></i>
                                            Вернуться к номерам
                                        </a>
                                    </div>

                                    <!-- Уведомление о минимальном сроке -->
                                    <div class="alert alert-warning mt-4" id="minStayAlert" style="display: none;">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <span id="minStayMessage"></span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Полезная информация -->
                <div class="row mt-4 g-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-calendar-check text-primary fs-1 mb-3"></i>
                                <h6 class="fw-bold mb-2">Гибкие даты</h6>
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
                                    SSL-шифрование данных, безопасные платежи
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
            </div>
        </div>
    </div>

    <!-- Модальное окно праздничных дней -->
    <div class="modal fade" id="holidaysModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Праздничные дни и особые периоды</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group list-group-flush">
                        @foreach($specialPeriods as $period)
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1">{{ $period->name }}</h6>
                                        <p class="text-muted small mb-0">
                                            {{ $period->start_date->format('d.m.Y') }} - {{ $period->end_date->format('d.m.Y') }}
                                        </p>
                                    </div>
                                    <span class="badge bg-warning text-dark">
                                    +{{ $period->price_multiplier * 100 - 100 }}%
                                </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .step-number {
            transition: all 0.3s;
        }

        .booking-calendar {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            background-color: #fff;
        }

        .flatpickr-calendar {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
        }

        .flatpickr-day.selected {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        .flatpickr-day.inRange {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-color: rgba(13, 110, 253, 0.1) !important;
        }

        .badge.bg-info {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .input-group-text {
            background-color: #f8f9fa;
        }

        .card {
            border-radius: 12px;
        }

        @media (max-width: 768px) {
            .booking-calendar {
                padding: 10px;
            }

            #calendar {
                height: 300px !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ru.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Инициализация календаря
            const checkInInput = document.getElementById('check_in');
            const checkOutInput = document.getElementById('check_out');
            const nightsCount = document.getElementById('nightsCount');
            const continueButton = document.getElementById('continueButton');
            const minStayAlert = document.getElementById('minStayAlert');
            const minStayMessage = document.getElementById('minStayMessage');

            let selectedDates = [];
            let unavailableDates = @json($unavailableDates);
            let specialPeriods = @json($specialPeriods);

            // Форматирование даты
            function formatDate(date) {
                return date.toLocaleDateString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }

            // Расчет количества ночей
            function calculateNights() {
                if (selectedDates.length === 2) {
                    const start = selectedDates[0];
                    const end = selectedDates[1];
                    const nights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));

                    if (nights > 0) {
                        nightsCount.textContent = `${nights} ${nights === 1 ? 'ночь' : nights < 5 ? 'ночи' : 'ночей'}`;

                        // Проверить минимальный срок проживания
                        checkMinStay(nights);

                        // Обновить поля ввода
                        checkInInput.value = formatDate(start);
                        checkOutInput.value = formatDate(end);

                        // Активировать кнопку
                        continueButton.disabled = false;
                        return;
                    }
                }

                nightsCount.textContent = '0 ночей';
                continueButton.disabled = true;
            }

            // Проверка минимального срока проживания
            function checkMinStay(nights) {
                const minStay = {{ $minStay ?? 1 }};

                if (nights < minStay) {
                    minStayAlert.style.display = 'block';
                    minStayMessage.textContent = `Минимальный срок проживания: ${minStay} ${minStay === 1 ? 'ночь' : minStay < 5 ? 'ночи' : 'ночей'}`;
                    continueButton.disabled = true;
                } else {
                    minStayAlert.style.display = 'none';
                    continueButton.disabled = false;
                }
            }

            // Инициализация Flatpickr
            const calendar = flatpickr("#calendar", {
                inline: true,
                mode: "range",
                minDate: "today",
                locale: "ru",
                disable: unavailableDates,
                onChange: function(selectedDates) {
                    window.selectedDates = selectedDates;
                    calculateNights();
                },
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    // Проверка специальных периодов
                    const date = dayElem.dateObj;
                    const dateStr = date.toISOString().split('T')[0];

                    // Проверить, является ли день праздничным
                    const specialPeriod = specialPeriods.find(period => {
                        return date >= new Date(period.start_date) && date <= new Date(period.end_date);
                    });

                    if (specialPeriod) {
                        dayElem.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
                        dayElem.title = `${specialPeriod.name} (+${specialPeriod.price_multiplier * 100 - 100}%)`;
                    }

                    // Проверить, занят ли день
                    if (unavailableDates.includes(dateStr)) {
                        dayElem.classList.add('notAvailable');
                        dayElem.innerHTML += "<span class='event busy'></span>";
                        dayElem.title = "Недоступно";
                    }
                }
            });

            // Инициализация datepicker для полей ввода
            flatpickr(".datepicker", {
                dateFormat: "d.m.Y",
                minDate: "today",
                locale: "ru",
                onChange: function(selectedDates, dateStr, instance) {
                    if (instance.input.id === 'check_in') {
                        // Обновить календарь
                        calendar.set('minDate', dateStr);
                        if (checkOutInput.value) {
                            selectedDates = [instance.selectedDates[0], new Date(checkOutInput.value.split('.').reverse().join('-'))];
                            calendar.setDate(selectedDates, true);
                        }
                    } else if (instance.input.id === 'check_out') {
                        if (checkInInput.value) {
                            selectedDates = [new Date(checkInInput.value.split('.').reverse().join('-')), instance.selectedDates[0]];
                            calendar.setDate(selectedDates, true);
                        }
                    }
                }
            });

            // Обновление количества гостей
            function updateTotalGuests() {
                const adults = parseInt(document.getElementById('adults').value) || 0;
                const children = parseInt(document.getElementById('children').value) || 0;
                const total = adults + children;
                document.getElementById('totalGuests').textContent = `Всего гостей: ${total}`;

                // Проверить максимальную вместимость
                const maxCapacity = {{ $maxCapacity ?? 10 }};
                if (total > maxCapacity) {
                    document.getElementById('totalGuests').className = 'mt-2 text-danger';
                    continueButton.disabled = true;
                } else {
                    document.getElementById('totalGuests').className = 'mt-2 text-muted';
                    continueButton.disabled = !selectedDates.length;
                }
            }

            document.getElementById('adults').addEventListener('input', updateTotalGuests);
            document.getElementById('children').addEventListener('input', updateTotalGuests);

            // Быстрый выбор продолжительности
            document.querySelectorAll('[data-days]').forEach(button => {
                button.addEventListener('click', function() {
                    const days = parseInt(this.dataset.days);
                    const startDate = new Date();
                    startDate.setDate(startDate.getDate() + 1); // Завтра

                    const endDate = new Date(startDate);
                    endDate.setDate(endDate.getDate() + days);

                    calendar.setDate([startDate, endDate], true);
                });
            });

            // Валидация формы
            document.getElementById('bookingStep1Form').addEventListener('submit', function(e) {
                if (!selectedDates.length) {
                    e.preventDefault();
                    showAlert('Пожалуйста, выберите даты проживания', 'warning');
                    return;
                }

                const adults = parseInt(document.getElementById('adults').value) || 0;
                const children = parseInt(document.getElementById('children').value) || 0;
                const totalGuests = adults + children;

                if (totalGuests === 0) {
                    e.preventDefault();
                    showAlert('Укажите количество гостей', 'warning');
                    return;
                }

                const roomsCount = parseInt(document.getElementById('rooms_count').value) || 0;
                if (roomsCount === 0) {
                    e.preventDefault();
                    showAlert('Укажите количество номеров', 'warning');
                    return;
                }

                // Проверить, что дата выезда позже даты заезда
                const checkIn = new Date(selectedDates[0]);
                const checkOut = new Date(selectedDates[1]);

                if (checkOut <= checkIn) {
                    e.preventDefault();
                    showAlert('Дата выезда должна быть позже даты заезда', 'warning');
                    return;
                }

                // Проверить минимальный срок
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                const minStay = {{ $minStay ?? 1 }};

                if (nights < minStay) {
                    e.preventDefault();
                    showAlert(`Минимальный срок проживания: ${minStay} ${minStay === 1 ? 'ночь' : minStay < 5 ? 'ночи' : 'ночей'}`, 'warning');
                    return;
                }
            });

            // Функция показа уведомлений
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
                alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

                document.querySelector('.card-body').prepend(alertDiv);

                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            // Инициализация
            updateTotalGuests();

            // Установить начальные даты в календарь, если они есть в запросе
            @if(request('check_in') && request('check_out'))
            const startDate = new Date('{{ request("check_in") }}'.split('.').reverse().join('-'));
            const endDate = new Date('{{ request("check_out") }}'.split('.').reverse().join('-'));
            calendar.setDate([startDate, endDate], true);
            @endif
        });
    </script>
@endpush
