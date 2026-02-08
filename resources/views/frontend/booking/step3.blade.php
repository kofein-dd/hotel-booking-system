@extends('layouts.app')

@section('title', 'Бронирование - Ваши данные')

@section('content')
    <div class="container py-5">
        <!-- Прогресс бар -->
        <div class="row mb-5">
            <div class="col">
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar" role="progressbar" style="width: 75%"></div>
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
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">3</div>
                        <div class="mt-2 small fw-bold">Данные</div>
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
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-4">
                        <h1 class="h3 fw-bold mb-2 text-center">
                            <i class="bi bi-person-lines-fill text-primary me-2"></i>
                            Ваши данные
                        </h1>
                        <p class="text-muted text-center mb-0">
                            Заполните информацию для бронирования
                        </p>
                    </div>

                    <div class="card-body p-4">
                        <form action="{{ route('booking.step4') }}" method="POST" id="bookingStep3Form">
                            @csrf

                            <!-- Скрытые поля -->
                            <input type="hidden" name="room_id" value="{{ $room->id }}">
                            <input type="hidden" name="check_in" value="{{ request('check_in') }}">
                            <input type="hidden" name="check_out" value="{{ request('check_out') }}">
                            <input type="hidden" name="adults" value="{{ request('adults', 1) }}">
                            <input type="hidden" name="children" value="{{ request('children', 0) }}">
                            <input type="hidden" name="rooms_count" value="{{ request('rooms_count', 1) }}">
                            <input type="hidden" name="total_price" id="totalPriceField" value="{{ $totalPrice }}">

                            @if(request()->has('promo_code'))
                                <input type="hidden" name="promo_code" value="{{ request('promo_code') }}">
                            @endif

                            @if(request()->has('services'))
                                @foreach(request('services', []) as $service)
                                    <input type="hidden" name="services[]" value="{{ $service }}">
                                @endforeach
                            @endif

                            <div class="row">
                                <!-- Левая колонка - информация о бронировании -->
                                <div class="col-lg-5 mb-4">
                                    <!-- Сводка бронирования -->
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-header bg-light border-0 py-3">
                                            <h5 class="fw-bold mb-0">
                                                <i class="bi bi-receipt text-primary me-2"></i>
                                                Сводка бронирования
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <!-- Номер -->
                                            <div class="d-flex align-items-start mb-4">
                                                @if($room->photos && count($room->photos) > 0)
                                                    <img src="{{ asset($room->photos[0]) }}"
                                                         alt="{{ $room->name }}"
                                                         class="rounded me-3"
                                                         style="width: 80px; height: 60px; object-fit: cover;">
                                                @endif
                                                <div>
                                                    <h6 class="fw-bold mb-1">{{ $room->name }}</h6>
                                                    <p class="text-muted small mb-0">
                                                        <i class="bi bi-people me-1"></i>
                                                        До {{ $room->capacity }} гостей
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Даты -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">Даты:</span>
                                                    <span class="fw-bold">
                                                    {{ \Carbon\Carbon::parse(request('check_in'))->translatedFormat('d F') }} -
                                                    {{ \Carbon\Carbon::parse(request('check_out'))->translatedFormat('d F Y') }}
                                                </span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Продолжительность:</span>
                                                    <span class="fw-bold">
                                                    {{ $nights }} {{ trans_choice('ночь|ночи|ночей', $nights) }}
                                                </span>
                                                </div>
                                            </div>

                                            <!-- Гости -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Гости:</span>
                                                    <span class="fw-bold">
                                                    {{ request('adults', 1) }} взр.
                                                    @if(request('children', 0) > 0)
                                                            , {{ request('children') }} дет.
                                                        @endif
                                                </span>
                                                </div>
                                                @if(request('rooms_count', 1) > 1)
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Номера:</span>
                                                        <span class="fw-bold">{{ request('rooms_count') }} шт.</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Дополнительные услуги -->
                                            @if($selectedServices->count() > 0)
                                                <div class="mb-3">
                                                    <h6 class="fw-bold mb-2">Дополнительные услуги:</h6>
                                                    <div class="list-group list-group-flush">
                                                        @foreach($selectedServices as $service)
                                                            <div class="list-group-item border-0 px-0 py-1 d-flex justify-content-between">
                                                                <small>{{ $service->name }}</small>
                                                                <small class="fw-bold">{{ number_format($service->price, 0, '', ' ') }} ₽</small>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Итоговая стоимость -->
                                            <div class="border-top pt-3">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="text-muted">Стоимость номера:</span>
                                                    <span class="fw-bold">{{ number_format($roomPrice, 0, '', ' ') }} ₽</span>
                                                </div>

                                                @if($servicesPrice > 0)
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted">Доп. услуги:</span>
                                                        <span class="fw-bold">{{ number_format($servicesPrice, 0, '', ' ') }} ₽</span>
                                                    </div>
                                                @endif

                                                @if($discountAmount > 0)
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="text-muted text-success">Скидка:</span>
                                                        <span class="fw-bold text-success">-{{ number_format($discountAmount, 0, '', ' ') }} ₽</span>
                                                    </div>
                                                @endif

                                                <div class="d-flex justify-content-between fw-bold fs-5">
                                                    <span>Итого к оплате:</span>
                                                    <span id="totalPriceDisplay">{{ number_format($totalPrice, 0, '', ' ') }} ₽</span>
                                                </div>

                                                @if($taxAmount > 0)
                                                    <div class="text-end small text-muted mt-1">
                                                        Включая налог {{ number_format($taxAmount, 0, '', ' ') }} ₽
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Информация о номере -->
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="bi bi-info-circle text-info me-2"></i>
                                                Условия бронирования
                                            </h6>
                                            <ul class="list-unstyled small text-muted">
                                                <li class="mb-2">
                                                    <i class="bi bi-check-circle text-success me-2"></i>
                                                    Бесплатная отмена до 24 часов до заезда
                                                </li>
                                                <li class="mb-2">
                                                    <i class="bi bi-check-circle text-success me-2"></i>
                                                    Оплата при заселении
                                                </li>
                                                <li class="mb-2">
                                                    <i class="bi bi-check-circle text-success me-2"></i>
                                                    Гарантия лучшей цены
                                                </li>
                                                <li>
                                                    <i class="bi bi-check-circle text-success me-2"></i>
                                                    Подтверждение в течение 2 часов
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Правая колонка - форма -->
                                <div class="col-lg-7">
                                    <!-- Информация о госте -->
                                    <div class="mb-5">
                                        <h5 class="fw-bold mb-4 border-bottom pb-3">
                                            <i class="bi bi-person-badge text-primary me-2"></i>
                                            Информация о главном госте
                                        </h5>

                                        <div class="row g-3">
                                            <!-- Имя -->
                                            <div class="col-md-6">
                                                <label for="first_name" class="form-label fw-bold">
                                                    Имя <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                                       id="first_name" name="first_name"
                                                       value="{{ old('first_name', auth()->user()->first_name ?? '') }}"
                                                       required>
                                                @error('first_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <!-- Фамилия -->
                                            <div class="col-md-6">
                                                <label for="last_name" class="form-label fw-bold">
                                                    Фамилия <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                                       id="last_name" name="last_name"
                                                       value="{{ old('last_name', auth()->user()->last_name ?? '') }}"
                                                       required>
                                                @error('last_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-6">
                                                <label for="email" class="form-label fw-bold">
                                                    Email <span class="text-danger">*</span>
                                                </label>
                                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                                       id="email" name="email"
                                                       value="{{ old('email', auth()->user()->email ?? '') }}"
                                                       required>
                                                @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <!-- Телефон -->
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label fw-bold">
                                                    Телефон <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-telephone"></i>
                                                </span>
                                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                                           id="phone" name="phone"
                                                           value="{{ old('phone', auth()->user()->phone ?? '') }}"
                                                           required>
                                                </div>
                                                @error('phone')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <!-- Гражданство -->
                                            <div class="col-md-6">
                                                <label for="nationality" class="form-label fw-bold">
                                                    Гражданство <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-select @error('nationality') is-invalid @enderror"
                                                        id="nationality" name="nationality" required>
                                                    <option value="">Выберите страну</option>
                                                    @foreach($countries as $code => $name)
                                                        <option value="{{ $code }}"
                                                            {{ old('nationality', auth()->user()->country ?? 'RU') == $code ? 'selected' : '' }}>
                                                            {{ $name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('nationality')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <!-- Дата рождения -->
                                            <div class="col-md-6">
                                                <label for="birth_date" class="form-label fw-bold">
                                                    Дата рождения
                                                </label>
                                                <input type="date" class="form-control @error('birth_date') is-invalid @enderror"
                                                       id="birth_date" name="birth_date"
                                                       value="{{ old('birth_date', auth()->user()->birth_date ? auth()->user()->birth_date->format('Y-m-d') : '') }}"
                                                       max="{{ date('Y-m-d') }}">
                                                @error('birth_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <!-- Паспортные данные -->
                                            <div class="col-md-12">
                                                <label for="passport_number" class="form-label fw-bold">
                                                    Номер паспорта
                                                </label>
                                                <input type="text" class="form-control @error('passport_number') is-invalid @enderror"
                                                       id="passport_number" name="passport_number"
                                                       value="{{ old('passport_number') }}">
                                                @error('passport_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <div class="form-text">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Требуется для заселения иностранных граждан
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Информация о втором госте (если нужно) -->
                                    @if(request('adults', 1) > 1)
                                        <div class="mb-5">
                                            <h5 class="fw-bold mb-4 border-bottom pb-3">
                                                <i class="bi bi-person-plus text-primary me-2"></i>
                                                Информация о втором госте
                                            </h5>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="guest2_first_name" class="form-label">
                                                        Имя второго гостя
                                                    </label>
                                                    <input type="text" class="form-control"
                                                           id="guest2_first_name" name="guest2_first_name"
                                                           value="{{ old('guest2_first_name') }}">
                                                </div>

                                                <div class="col-md-6">
                                                    <label for="guest2_last_name" class="form-label">
                                                        Фамилия второго гостя
                                                    </label>
                                                    <input type="text" class="form-control"
                                                           id="guest2_last_name" name="guest2_last_name"
                                                           value="{{ old('guest2_last_name') }}">
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Дополнительные пожелания -->
                                    <div class="mb-5">
                                        <h5 class="fw-bold mb-4 border-bottom pb-3">
                                            <i class="bi bi-chat-heart text-primary me-2"></i>
                                            Дополнительные пожелания
                                        </h5>

                                        <div class="row g-3">
                                            <!-- Время заезда -->
                                            <div class="col-md-6">
                                                <label for="check_in_time" class="form-label fw-bold">
                                                    Примерное время заезда
                                                </label>
                                                <select class="form-select" id="check_in_time" name="check_in_time">
                                                    <option value="">Не важно</option>
                                                    <option value="12:00" {{ old('check_in_time') == '12:00' ? 'selected' : '' }}>12:00 - 14:00</option>
                                                    <option value="14:00" {{ old('check_in_time') == '14:00' ? 'selected' : '' }}>14:00 - 16:00</option>
                                                    <option value="16:00" {{ old('check_in_time') == '16:00' ? 'selected' : '' }}>16:00 - 18:00</option>
                                                    <option value="18:00" {{ old('check_in_time') == '18:00' ? 'selected' : '' }}>18:00 - 20:00</option>
                                                    <option value="20:00" {{ old('check_in_time') == '20:00' ? 'selected' : '' }}>После 20:00</option>
                                                </select>
                                            </div>

                                            <!-- Особые пожелания -->
                                            <div class="col-md-12">
                                                <label for="special_requests" class="form-label fw-bold">
                                                    Особые пожелания
                                                </label>
                                                <textarea class="form-control @error('special_requests') is-invalid @enderror"
                                                          id="special_requests" name="special_requests"
                                                          rows="3" placeholder="Например: поздний заезд, кровать для ребенка, и т.д.">{{ old('special_requests') }}</textarea>
                                                @error('special_requests')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <div class="form-text">
                                                    <span id="specialRequestsCounter">0</span>/500 символов
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Способ оплаты -->
                                    <div class="mb-5">
                                        <h5 class="fw-bold mb-4 border-bottom pb-3">
                                            <i class="bi bi-credit-card text-primary me-2"></i>
                                            Способ оплаты
                                        </h5>

                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="radio" name="payment_method"
                                                           id="payment_cash" value="cash"
                                                        {{ old('payment_method', 'cash') == 'cash' ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-bold" for="payment_cash">
                                                        <i class="bi bi-cash text-success me-2"></i>
                                                        Оплата при заселении
                                                    </label>
                                                    <div class="form-text ms-4">
                                                        Оплатите наличными или картой при заселении
                                                    </div>
                                                </div>

                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="radio" name="payment_method"
                                                           id="payment_online" value="online"
                                                        {{ old('payment_method') == 'online' ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-bold" for="payment_online">
                                                        <i class="bi bi-credit-card text-primary me-2"></i>
                                                        Онлайн оплата сейчас
                                                    </label>
                                                    <div class="form-text ms-4">
                                                        Безопасная оплата картой онлайн. Гарантия бронирования.
                                                    </div>
                                                </div>

                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method"
                                                           id="payment_invoice" value="invoice"
                                                        {{ old('payment_method') == 'invoice' ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-bold" for="payment_invoice">
                                                        <i class="bi bi-receipt text-warning me-2"></i>
                                                        Выставить счет для юр. лица
                                                    </label>
                                                    <div class="form-text ms-4">
                                                        Для организаций и компаний
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Банковские карты (если выбрана онлайн оплата) -->
                                            <div class="col-12 mt-3" id="cardDetails" style="display: none;">
                                                <div class="card border-primary">
                                                    <div class="card-body">
                                                        <h6 class="fw-bold mb-3">Данные банковской карты</h6>

                                                        <div class="row g-3">
                                                            <div class="col-md-12">
                                                                <label for="card_number" class="form-label">
                                                                    Номер карты
                                                                </label>
                                                                <div class="input-group">
                                                                <span class="input-group-text">
                                                                    <i class="bi bi-credit-card"></i>
                                                                </span>
                                                                    <input type="text" class="form-control"
                                                                           id="card_number" name="card_number"
                                                                           placeholder="1234 5678 9012 3456"
                                                                           maxlength="19">
                                                                    <span class="input-group-text">
                                                                    <i class="bi bi-shield-check text-success"></i>
                                                                </span>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <label for="card_expiry" class="form-label">
                                                                    Срок действия
                                                                </label>
                                                                <input type="text" class="form-control"
                                                                       id="card_expiry" name="card_expiry"
                                                                       placeholder="ММ/ГГ">
                                                            </div>

                                                            <div class="col-md-6">
                                                                <label for="card_cvc" class="form-label">
                                                                    CVC/CVV
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control"
                                                                           id="card_cvc" name="card_cvc"
                                                                           placeholder="123"
                                                                           maxlength="4">
                                                                    <span class="input-group-text">
                                                                    <i class="bi bi-key"></i>
                                                                </span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                           id="save_card" name="save_card">
                                                                    <label class="form-check-label small" for="save_card">
                                                                        Сохранить карту для будущих платежей
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Уведомления -->
                                    <div class="mb-5">
                                        <h5 class="fw-bold mb-4 border-bottom pb-3">
                                            <i class="bi bi-bell text-primary me-2"></i>
                                            Уведомления
                                        </h5>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox"
                                                   id="email_notifications" name="email_notifications"
                                                {{ old('email_notifications', true) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="email_notifications">
                                                Получать уведомления по email
                                            </label>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox"
                                                   id="sms_notifications" name="sms_notifications"
                                                {{ old('sms_notifications', true) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="sms_notifications">
                                                Получать уведомления по SMS
                                            </label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   id="reminder_notifications" name="reminder_notifications"
                                                {{ old('reminder_notifications', true) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="reminder_notifications">
                                                Напомнить за день до заезда
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Соглашение -->
                                    <div class="mb-5">
                                        <div class="form-check">
                                            <input class="form-check-input @error('terms') is-invalid @enderror"
                                                   type="checkbox" name="terms" id="terms" required>
                                            <label class="form-check-label" for="terms">
                                                Я согласен с
                                                <a href="{{ route('pages.show', 'terms') }}" target="_blank" class="text-decoration-none">
                                                    условиями бронирования
                                                </a>
                                                и
                                                <a href="{{ route('pages.show', 'privacy') }}" target="_blank" class="text-decoration-none">
                                                    политикой конфиденциальности
                                                </a>
                                                <span class="text-danger">*</span>
                                            </label>
                                            @error('terms')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Кнопки -->
                                    <div class="d-flex justify-content-between">
                                        <a href="{{ route('booking.step2') . '?' . http_build_query(request()->except(['_token'])) }}"
                                           class="btn btn-outline-secondary px-4">
                                            <i class="bi bi-arrow-left me-2"></i>Назад
                                        </a>

                                        <button type="submit" class="btn btn-primary px-5" id="submitButton">
                                            <i class="bi bi-check-circle me-2"></i>
                                            Продолжить
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Защита данных -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body text-center p-4">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <i class="bi bi-shield-check text-success fs-1"></i>
                                <div class="mt-2 fw-bold">Безопасные данные</div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <i class="bi bi-lock text-primary fs-1"></i>
                                <div class="mt-2 fw-bold">SSL-шифрование</div>
                            </div>
                            <div class="col-md-4">
                                <i class="bi bi-credit-card text-warning fs-1"></i>
                                <div class="mt-2 fw-bold">Безопасные платежи</div>
                            </div>
                        </div>
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

        .form-label.required::after {
            content: " *";
            color: #dc3545;
        }

        .card.border-primary {
            border-width: 2px !important;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            cursor: pointer;
        }

        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        .border-bottom {
            border-bottom: 2px solid #dee2e6 !important;
        }

        #submitButton:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem !important;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форматирование телефона
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/\D/g, '');

                    if (value.startsWith('7') || value.startsWith('8')) {
                        value = value.substring(1);
                    }

                    if (value.length > 0) {
                        value = '+7 (' + value;
                    }
                    if (value.length > 7) {
                        value = value.substring(0, 7) + ') ' + value.substring(7);
                    }
                    if (value.length > 12) {
                        value = value.substring(0, 12) + '-' + value.substring(12);
                    }
                    if (value.length > 15) {
                        value = value.substring(0, 15) + '-' + value.substring(15);
                    }

                    this.value = value.substring(0, 18);
                });
            }

            // Форматирование номера карты
            const cardNumberInput = document.getElementById('card_number');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/\D/g, '');
                    let formatted = '';

                    for (let i = 0; i < value.length; i++) {
                        if (i > 0 && i % 4 === 0) {
                            formatted += ' ';
                        }
                        formatted += value[i];
                    }

                    this.value = formatted.substring(0, 19);

                    // Определить тип карты
                    detectCardType(value);
                });
            }

            // Определение типа карты
            function detectCardType(number) {
                const cardTypes = {
                    visa: /^4/,
                    mastercard: /^5[1-5]/,
                    amex: /^3[47]/,
                    mir: /^220[0-4]/
                };

                const cardIcon = document.getElementById('cardIcon');
                if (!cardIcon) return;

                for (const [type, pattern] of Object.entries(cardTypes)) {
                    if (pattern.test(number)) {
                        cardIcon.className = `bi bi-credit-card text-${type === 'visa' ? 'primary' :
                            type === 'mastercard' ? 'warning' :
                                type === 'amex' ? 'info' : 'success'}`;
                        return;
                    }
                }

                cardIcon.className = 'bi bi-credit-card';
            }

            // Форматирование срока действия карты
            const cardExpiryInput = document.getElementById('card_expiry');
            if (cardExpiryInput) {
                cardExpiryInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/\D/g, '');

                    if (value.length >= 2) {
                        value = value.substring(0, 2) + '/' + value.substring(2, 4);
                    }

                    this.value = value.substring(0, 5);
                });
            }

            // Показ/скрытие деталей карты
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            const cardDetails = document.getElementById('cardDetails');

            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    if (this.value === 'online') {
                        cardDetails.style.display = 'block';
                    } else {
                        cardDetails.style.display = 'none';
                    }
                });
            });

            // Счетчик символов для особых пожеланий
            const specialRequests = document.getElementById('special_requests');
            const specialRequestsCounter = document.getElementById('specialRequestsCounter');

            if (specialRequests && specialRequestsCounter) {
                specialRequestsCounter.textContent = specialRequests.value.length;

                specialRequests.addEventListener('input', function() {
                    specialRequestsCounter.textContent = this.value.length;

                    if (this.value.length > 500) {
                        specialRequestsCounter.classList.add('text-danger');
                        this.classList.add('is-invalid');
                    } else {
                        specialRequestsCounter.classList.remove('text-danger');
                        this.classList.remove('is-invalid');
                    }
                });
            }

            // Валидация формы
            const form = document.getElementById('bookingStep3Form');
            const submitButton = document.getElementById('submitButton');

            if (form && submitButton) {
                let isSubmitting = false;

                form.addEventListener('submit', function(e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return;
                    }

                    // Базовые проверки
                    const firstName = document.getElementById('first_name').value.trim();
                    const lastName = document.getElementById('last_name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    const nationality = document.getElementById('nationality').value;
                    const terms = document.getElementById('terms').checked;

                    if (!firstName || !lastName || !email || !phone || !nationality || !terms) {
                        e.preventDefault();
                        showAlert('Пожалуйста, заполните все обязательные поля', 'warning');
                        return;
                    }

                    // Валидация email
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        showAlert('Пожалуйста, введите корректный email адрес', 'warning');
                        return;
                    }

                    // Валидация телефона
                    const phoneRegex = /^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/;
                    if (!phoneRegex.test(phone)) {
                        e.preventDefault();
                        showAlert('Пожалуйста, введите корректный номер телефона', 'warning');
                        return;
                    }

                    // Проверка онлайн оплаты
                    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    if (paymentMethod && paymentMethod.value === 'online') {
                        const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                        const cardExpiry = document.getElementById('card_expiry').value;
                        const cardCvc = document.getElementById('card_cvc').value;

                        if (!cardNumber || !cardExpiry || !cardCvc) {
                            e.preventDefault();
                            showAlert('Пожалуйста, заполните данные банковской карты', 'warning');
                            return;
                        }

                        // Валидация номера карты (простая проверка Луна)
                        if (!validateCardNumber(cardNumber)) {
                            e.preventDefault();
                            showAlert('Номер карты некорректен. Пожалуйста, проверьте.', 'warning');
                            return;
                        }

                        // Валидация срока действия
                        const [month, year] = cardExpiry.split('/');
                        const currentYear = new Date().getFullYear() % 100;
                        const currentMonth = new Date().getMonth() + 1;

                        if (!month || !year ||
                            parseInt(month) < 1 || parseInt(month) > 12 ||
                            parseInt(year) < currentYear ||
                            (parseInt(year) === currentYear && parseInt(month) < currentMonth)) {
                            e.preventDefault();
                            showAlert('Срок действия карты истек или некорректен', 'warning');
                            return;
                        }

                        // Валидация CVC
                        if (cardCvc.length < 3 || cardCvc.length > 4 || !/^\d+$/.test(cardCvc)) {
                            e.preventDefault();
                            showAlert('CVC/CVV код некорректен', 'warning');
                            return;
                        }
                    }

                    // Блокировать кнопку
                    isSubmitting = true;
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Обработка...';
                });
            }

            // Алгоритм Луна для проверки номера карты
            function validateCardNumber(cardNumber) {
                cardNumber = cardNumber.replace(/\D/g, '');

                let sum = 0;
                let isEven = false;

                for (let i = cardNumber.length - 1; i >= 0; i--) {
                    let digit = parseInt(cardNumber.charAt(i), 10);

                    if (isEven) {
                        digit *= 2;
                        if (digit > 9) {
                            digit -= 9;
                        }
                    }

                    sum += digit;
                    isEven = !isEven;
                }

                return (sum % 10) === 0;
            }

            // Автозаполнение данных из профиля (если пользователь авторизован)
            @auth
            function fillFromProfile() {
                const profileData = {
                    first_name: '{{ auth()->user()->first_name ?? "" }}',
                    last_name: '{{ auth()->user()->last_name ?? "" }}',
                    email: '{{ auth()->user()->email ?? "" }}',
                    phone: '{{ auth()->user()->phone ?? "" }}',
                    nationality: '{{ auth()->user()->country ?? "RU" }}',
                    birth_date: '{{ auth()->user()->birth_date ? auth()->user()->birth_date->format("Y-m-d") : "" }}'
                };

                Object.keys(profileData).forEach(key => {
                    const element = document.getElementById(key);
                    if (element && profileData[key] && !element.value) {
                        element.value = profileData[key];
                    }
                });
            }

            // Кнопка автозаполнения
            const autoFillBtn = document.createElement('button');
            autoFillBtn.type = 'button';
            autoFillBtn.className = 'btn btn-sm btn-outline-info mt-2';
            autoFillBtn.innerHTML = '<i class="bi bi-person-check me-1"></i>Заполнить из профиля';
            autoFillBtn.addEventListener('click', fillFromProfile);

            const guestSection = document.querySelector('h5:contains("Информация о главном госте")')?.parentElement;
            if (guestSection) {
                guestSection.appendChild(autoFillBtn);
            }
            @endauth

            // Сохранение формы в localStorage при изменении
            function saveFormData() {
                const formData = {};
                const inputs = form.querySelectorAll('input, select, textarea');

                inputs.forEach(input => {
                    if (input.name && !input.name.includes('card_')) { // Не сохраняем данные карты
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            formData[input.name] = input.checked;
                        } else {
                            formData[input.name] = input.value;
                        }
                    }
                });

                localStorage.setItem('bookingFormData', JSON.stringify(formData));
            }

            // Восстановление формы из localStorage
            function loadFormData() {
                const savedData = localStorage.getItem('bookingFormData');
                if (savedData) {
                    const formData = JSON.parse(savedData);

                    Object.keys(formData).forEach(key => {
                        const element = form.querySelector(`[name="${key}"]`);
                        if (element) {
                            if (element.type === 'checkbox' || element.type === 'radio') {
                                element.checked = formData[key];
                            } else {
                                element.value = formData[key];
                            }

                            // Триггер событий для обновления UI
                            if (element.type === 'radio' && element.checked) {
                                element.dispatchEvent(new Event('change'));
                            }
                        }
                    });
                }
            }

            // Сохранять форму при изменении
            form?.addEventListener('input', saveFormData);
            form?.addEventListener('change', saveFormData);

            // Загрузить данные при загрузке страницы
            loadFormData();

            // Функция показа уведомлений
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
                alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

                form.prepend(alertDiv);

                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            // Таймер на заполнение формы (опционально)
            let formTimer = 10 * 60; // 10 минут
            const timerElement = document.createElement('div');
            timerElement.className = 'alert alert-warning mt-3';
            timerElement.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-clock-history me-3 fs-4"></i>
                <div>
                    <h6 class="alert-heading mb-1">Внимание!</h6>
                    <p class="mb-0">Ваша форма будет автоматически сохранена. Осталось: <span id="timerCount">${formTimer}</span> секунд</p>
                </div>
            </div>
        `;

            form?.parentElement.insertBefore(timerElement, form);

            const timerInterval = setInterval(() => {
                formTimer--;
                document.getElementById('timerCount').textContent = formTimer;

                if (formTimer <= 0) {
                    clearInterval(timerInterval);
                    saveFormData();
                    timerElement.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-3 fs-4"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Форма сохранена</h6>
                            <p class="mb-0">Ваши данные сохранены. Вы можете продолжить позже.</p>
                        </div>
                    </div>
                `;
                }
            }, 1000);
        });
    </script>
@endpush
