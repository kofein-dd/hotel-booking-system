@extends('layouts.app')

@section('title', 'Редактирование профиля')

@section('content')
    <div class="container py-5">
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item"><a href="{{ route('profile.index') }}">Личный кабинет</a></li>
                <li class="breadcrumb-item active" aria-current="page">Редактирование профиля</li>
            </ol>
        </nav>

        <div class="row">
            <!-- Сайдбар -->
            <div class="col-lg-3 mb-4">
                @include('frontend.profile.partials.sidebar')
            </div>

            <!-- Основной контент -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-4">
                        <h1 class="h3 fw-bold mb-0">
                            <i class="bi bi-person-gear text-primary me-2"></i>
                            Редактирование профиля
                        </h1>
                        <p class="text-muted mb-0 mt-2 small">Обновите информацию о себе</p>
                    </div>

                    <div class="card-body p-4">
                        <!-- Сообщения об успехе/ошибках -->
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Пожалуйста, исправьте ошибки в форме
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <!-- Форма редактирования -->
                        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="profileForm">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <!-- Левая колонка -->
                                <div class="col-lg-6">
                                    <!-- Аватар -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Аватар</label>
                                        <div class="d-flex align-items-center">
                                            <div class="me-4">
                                                <div class="avatar-upload position-relative">
                                                    <div class="avatar-preview rounded-circle overflow-hidden"
                                                         style="width: 120px; height: 120px; background-color: #f8f9fa;">
                                                        @if(Auth::user()->avatar_url)
                                                            <img id="avatarPreview" src="{{ Auth::user()->avatar_url }}"
                                                                 alt="Текущий аватар" class="w-100 h-100 object-fit-cover">
                                                        @else
                                                            <div id="avatarPreview" class="w-100 h-100 d-flex align-items-center justify-content-center bg-primary text-white fs-2">
                                                                {{ substr(Auth::user()->name, 0, 1) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <label for="avatar" class="avatar-edit position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 cursor-pointer">
                                                        <i class="bi bi-camera"></i>
                                                        <input type="file" id="avatar" name="avatar" class="d-none" accept="image/*">
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <p class="text-muted small mb-2">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Загрузите изображение JPG, PNG или GIF (максимум 2MB)
                                                </p>
                                                <button type="button" id="removeAvatar" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash me-1"></i>Удалить аватар
                                                </button>
                                                @error('avatar')
                                                <div class="text-danger small mt-2">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Основная информация -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3 border-bottom pb-2">Основная информация</h6>

                                        <!-- Имя -->
                                        <div class="mb-3">
                                            <label for="name" class="form-label fw-bold">
                                                <i class="bi bi-person-fill me-2"></i>Имя и фамилия
                                            </label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                                   id="name" name="name" value="{{ old('name', Auth::user()->name) }}"
                                                   required>
                                            @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Email -->
                                        <div class="mb-3">
                                            <label for="email" class="form-label fw-bold">
                                                <i class="bi bi-envelope-fill me-2"></i>Email адрес
                                            </label>
                                            <div class="input-group">
                                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                                       id="email" name="email" value="{{ old('email', Auth::user()->email) }}"
                                                       required>
                                                @if(Auth::user()->email_verified_at)
                                                    <span class="input-group-text bg-success text-white">
                                                    <i class="bi bi-check-circle"></i>
                                                </span>
                                                @else
                                                    <span class="input-group-text bg-warning text-white">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                </span>
                                                @endif
                                            </div>
                                            @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            @if(!Auth::user()->email_verified_at)
                                                <div class="form-text text-warning">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    Email не подтвержден.
                                                    <a href="{{ route('verification.resend') }}" class="text-decoration-none">
                                                        Отправить подтверждение еще раз
                                                    </a>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Телефон -->
                                        <div class="mb-3">
                                            <label for="phone" class="form-label fw-bold">
                                                <i class="bi bi-telephone-fill me-2"></i>Телефон
                                            </label>
                                            <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                                   id="phone" name="phone" value="{{ old('phone', Auth::user()->phone) }}"
                                                   placeholder="+7 (999) 123-45-67">
                                            @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Правая колонка -->
                                <div class="col-lg-6">
                                    <!-- Дополнительная информация -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3 border-bottom pb-2">Дополнительная информация</h6>

                                        <!-- Дата рождения -->
                                        <div class="mb-3">
                                            <label for="birth_date" class="form-label fw-bold">
                                                <i class="bi bi-calendar-heart me-2"></i>Дата рождения
                                            </label>
                                            <input type="date" class="form-control @error('birth_date') is-invalid @enderror"
                                                   id="birth_date" name="birth_date"
                                                   value="{{ old('birth_date', Auth::user()->birth_date ? Auth::user()->birth_date->format('Y-m-d') : '') }}"
                                                   max="{{ date('Y-m-d') }}">
                                            @error('birth_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Укажите для персонализированных предложений
                                            </div>
                                        </div>

                                        <!-- Пол -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="bi bi-gender-ambiguous me-2"></i>Пол
                                            </label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="gender"
                                                           id="gender_male" value="male"
                                                        {{ old('gender', Auth::user()->gender) == 'male' ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="gender_male">
                                                        Мужской
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="gender"
                                                           id="gender_female" value="female"
                                                        {{ old('gender', Auth::user()->gender) == 'female' ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="gender_female">
                                                        Женский
                                                    </label>
                                                </div>
                                            </div>
                                            @error('gender')
                                            <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Страна -->
                                        <div class="mb-3">
                                            <label for="country" class="form-label fw-bold">
                                                <i class="bi bi-globe me-2"></i>Страна
                                            </label>
                                            <select class="form-select @error('country') is-invalid @enderror"
                                                    id="country" name="country">
                                                <option value="">Выберите страну</option>
                                                @foreach($countries as $code => $name)
                                                    <option value="{{ $code }}"
                                                        {{ old('country', Auth::user()->country) == $code ? 'selected' : '' }}>
                                                        {{ $name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('country')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Город -->
                                        <div class="mb-3">
                                            <label for="city" class="form-label fw-bold">
                                                <i class="bi bi-geo-alt me-2"></i>Город
                                            </label>
                                            <input type="text" class="form-control @error('city') is-invalid @enderror"
                                                   id="city" name="city" value="{{ old('city', Auth::user()->city) }}">
                                            @error('city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Адрес -->
                                        <div class="mb-3">
                                            <label for="address" class="form-label fw-bold">
                                                <i class="bi bi-house-door me-2"></i>Адрес
                                            </label>
                                            <textarea class="form-control @error('address') is-invalid @enderror"
                                                      id="address" name="address" rows="2">{{ old('address', Auth::user()->address) }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- О себе -->
                                        <div class="mb-3">
                                            <label for="about" class="form-label fw-bold">
                                                <i class="bi bi-chat-left-text me-2"></i>О себе
                                            </label>
                                            <textarea class="form-control @error('about') is-invalid @enderror"
                                                      id="about" name="about" rows="3"
                                                      placeholder="Расскажите немного о себе...">{{ old('about', Auth::user()->about) }}</textarea>
                                            @error('about')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <span id="aboutCounter">0</span>/500 символов
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Предпочтения -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3 border-bottom pb-2">Предпочтения</h6>

                                        <!-- Язык -->
                                        <div class="mb-3">
                                            <label for="language" class="form-label fw-bold">
                                                <i class="bi bi-translate me-2"></i>Язык интерфейса
                                            </label>
                                            <select class="form-select" id="language" name="language">
                                                <option value="ru" {{ old('language', Auth::user()->language) == 'ru' ? 'selected' : '' }}>Русский</option>
                                                <option value="en" {{ old('language', Auth::user()->language) == 'en' ? 'selected' : '' }}>English</option>
                                            </select>
                                        </div>

                                        <!-- Часовой пояс -->
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label fw-bold">
                                                <i class="bi bi-clock me-2"></i>Часовой пояс
                                            </label>
                                            <select class="form-select" id="timezone" name="timezone">
                                                @foreach($timezones as $tz)
                                                    <option value="{{ $tz }}"
                                                        {{ old('timezone', Auth::user()->timezone) == $tz ? 'selected' : '' }}>
                                                        {{ $tz }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Предпочтения рассылки -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="bi bi-envelope-paper me-2"></i>Рассылка
                                            </label>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox"
                                                       id="newsletter" name="newsletter"
                                                    {{ old('newsletter', Auth::user()->newsletter) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="newsletter">
                                                    Получать новости и специальные предложения
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       id="promo_notifications" name="promo_notifications"
                                                    {{ old('promo_notifications', Auth::user()->promo_notifications) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="promo_notifications">
                                                    Уведомления об акциях и скидках
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Кнопки -->
                            <div class="row mt-4 pt-3 border-top">
                                <div class="col-md-6">
                                    <a href="{{ route('profile.index') }}" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-arrow-left me-2"></i>Отмена
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-primary w-100" id="saveButton">
                                        <i class="bi bi-save me-2"></i>Сохранить изменения
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Дополнительные действия -->
                    <div class="card-footer bg-light border-0 p-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-shield-check text-info me-2"></i>
                            Дополнительные действия
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <a href="{{ route('profile.security') }}" class="btn btn-outline-info w-100">
                                    <i class="bi bi-key me-2"></i>Сменить пароль
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('profile.verify-identity') }}" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-person-badge me-2"></i>Верификация
                                </a>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal"
                                        data-bs-target="#deleteAccountModal">
                                    <i class="bi bi-trash me-2"></i>Удалить аккаунт
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Информация о последнем обновлении -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                                <i class="bi bi-clock-history me-1"></i>
                                Последнее обновление: {{ Auth::user()->updated_at->diffForHumans() }}
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                ID пользователя: {{ Auth::user()->id }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно удаления аккаунта -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Удаление аккаунта
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Это действие невозможно отменить!
                    </div>
                    <p>При удалении аккаунта:</p>
                    <ul>
                        <li>Все ваши данные будут удалены без возможности восстановления</li>
                        <li>Активные бронирования будут отменены</li>
                        <li>История платежей будет удалена</li>
                        <li>Вы потеряете доступ ко всем сервисам</li>
                    </ul>
                    <p class="fw-bold">Вы уверены, что хотите удалить свой аккаунт?</p>

                    <form method="POST" action="{{ route('profile.destroy') }}" id="deleteAccountForm">
                        @csrf
                        @method('DELETE')
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">
                                Для подтверждения введите ваш пароль:
                            </label>
                            <input type="password" class="form-control" id="confirmPassword" name="password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" form="deleteAccountForm" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Удалить аккаунт
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .avatar-upload {
            position: relative;
            display: inline-block;
        }

        .avatar-edit {
            cursor: pointer;
            transition: all 0.3s;
        }

        .avatar-edit:hover {
            background-color: #0b5ed7 !important;
            transform: scale(1.1);
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .object-fit-cover {
            object-fit: cover;
        }

        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        .border-bottom {
            border-bottom: 2px solid #dee2e6 !important;
        }

        #saveButton:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Предпросмотр аватара
            const avatarInput = document.getElementById('avatar');
            const avatarPreview = document.getElementById('avatarPreview');
            const removeAvatarBtn = document.getElementById('removeAvatar');

            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        if (file.size > 2 * 1024 * 1024) {
                            alert('Файл слишком большой. Максимальный размер: 2MB');
                            this.value = '';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (avatarPreview.tagName === 'IMG') {
                                avatarPreview.src = e.target.result;
                            } else {
                                avatarPreview.innerHTML = `<img src="${e.target.result}" class="w-100 h-100 object-fit-cover">`;
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (removeAvatarBtn) {
                removeAvatarBtn.addEventListener('click', function() {
                    if (avatarPreview.tagName === 'IMG') {
                        avatarPreview.src = '';
                        avatarPreview.style.display = 'none';
                    } else {
                        avatarPreview.innerHTML = `<div class="w-100 h-100 d-flex align-items-center justify-content-center bg-primary text-white fs-2">
                        {{ substr(Auth::user()->name, 0, 1) }}
                        </div>`;
                    }

                    if (avatarInput) {
                        avatarInput.value = '';
                    }

                    // Отправить запрос на удаление аватара
                    fetch('{{ route("profile.avatar.destroy") }}', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('Аватар успешно удален', 'success');
                            }
                        });
                });
            }

            // Счетчик символов для поля "О себе"
            const aboutTextarea = document.getElementById('about');
            const aboutCounter = document.getElementById('aboutCounter');

            if (aboutTextarea && aboutCounter) {
                aboutCounter.textContent = aboutTextarea.value.length;

                aboutTextarea.addEventListener('input', function() {
                    aboutCounter.textContent = this.value.length;

                    if (this.value.length > 500) {
                        aboutCounter.classList.add('text-danger');
                        this.classList.add('is-invalid');
                    } else {
                        aboutCounter.classList.remove('text-danger');
                        this.classList.remove('is-invalid');
                    }
                });
            }

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

            // Валидация формы
            const profileForm = document.getElementById('profileForm');
            const saveButton = document.getElementById('saveButton');

            if (profileForm && saveButton) {
                let isSubmitting = false;

                profileForm.addEventListener('submit', function(e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return;
                    }

                    // Базовые проверки
                    const name = document.getElementById('name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const about = document.getElementById('about')?.value.trim() || '';

                    if (!name) {
                        e.preventDefault();
                        showAlert('Пожалуйста, введите ваше имя', 'danger');
                        return;
                    }

                    if (!email) {
                        e.preventDefault();
                        showAlert('Пожалуйста, введите email', 'danger');
                        return;
                    }

                    if (about.length > 500) {
                        e.preventDefault();
                        showAlert('Поле "О себе" не должно превышать 500 символов', 'danger');
                        return;
                    }

                    // Блокировать кнопку
                    isSubmitting = true;
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Сохранение...';
                });
            }

            // Автозаполнение города по стране
            const countrySelect = document.getElementById('country');
            const cityInput = document.getElementById('city');

            if (countrySelect && cityInput) {
                const citiesByCountry = {
                    'RU': ['Москва', 'Санкт-Петербург', 'Сочи', 'Казань', 'Екатеринбург', 'Новосибирск'],
                    'UA': ['Киев', 'Одесса', 'Харьков', 'Львов', 'Днепр'],
                    'BY': ['Минск', 'Гомель', 'Витебск', 'Гродно', 'Брест'],
                    'KZ': ['Алматы', 'Нур-Султан', 'Шымкент', 'Караганда', 'Актобе']
                };

                countrySelect.addEventListener('change', function() {
                    const country = this.value;
                    const datalist = document.getElementById('citySuggestions') ||
                        (function() {
                            const dl = document.createElement('datalist');
                            dl.id = 'citySuggestions';
                            document.body.appendChild(dl);
                            return dl;
                        })();

                    cityInput.setAttribute('list', 'citySuggestions');
                    datalist.innerHTML = '';

                    if (citiesByCountry[country]) {
                        citiesByCountry[country].forEach(city => {
                            const option = document.createElement('option');
                            option.value = city;
                            datalist.appendChild(option);
                        });
                    }
                });
            }

            // Валидация даты рождения
            const birthDateInput = document.getElementById('birth_date');
            if (birthDateInput) {
                const today = new Date().toISOString().split('T')[0];
                birthDateInput.max = today;

                // Установить минимальный возраст 120 лет назад
                const minDate = new Date();
                minDate.setFullYear(minDate.getFullYear() - 120);
                birthDateInput.min = minDate.toISOString().split('T')[0];
            }

            // Функции уведомлений
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

                profileForm.prepend(alertDiv);

                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            function showToast(message, type) {
                // Реализация тостов (можно использовать Bootstrap Toast)
                console.log(`${type}: ${message}`);
            }
        });
    </script>
@endpush
