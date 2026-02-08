<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <!-- Логотип и название -->
        <a class="navbar-brand" href="{{ route('home') }}">
            @if(config('app.logo'))
                <img src="{{ asset(config('app.logo')) }}" alt="{{ config('app.name') }}" height="40">
            @else
                <span class="fw-bold text-primary">Отель у Моря</span>
            @endif
        </a>

        <!-- Кнопка для мобильных -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Основное меню -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                       href="{{ route('home') }}">
                        Главная
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('hotel.*') ? 'active' : '' }}"
                       href="#" role="button" data-bs-toggle="dropdown">
                        Об отеле
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('hotel.about') ? 'active' : '' }}"
                               href="{{ route('hotel.about') }}">
                                О нас
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('hotel.gallery') ? 'active' : '' }}"
                               href="{{ route('hotel.gallery') }}">
                                Галерея
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('hotel.contacts') ? 'active' : '' }}"
                               href="{{ route('hotel.contacts') }}">
                                Контакты отеля
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('rooms.index') ? 'active' : '' }}"
                       href="{{ route('rooms.index') }}">
                        Номера
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('faq.index') ? 'active' : '' }}"
                       href="{{ route('faq.index') }}">
                        FAQ
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('contact.index') ? 'active' : '' }}"
                       href="{{ route('contact.index') }}">
                        Контакты
                    </a>
                </li>
            </ul>

            <!-- Правая часть навбара -->
            <ul class="navbar-nav ms-auto">
                <!-- Поиск (только на десктопе) -->
                <li class="nav-item d-none d-lg-block">
                    <form class="d-flex" action="{{ route('search.rooms') }}" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm"
                                   placeholder="Поиск номеров..." name="query">
                            <button class="btn btn-outline-primary btn-sm" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </li>

                @auth
                    <!-- Уведомления -->
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" role="button"
                           data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            @if(auth()->user()->unreadNotifications->count() > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    {{ auth()->user()->unreadNotifications->count() }}
                                </span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Уведомления</h6></li>
                            @if(auth()->user()->notifications->take(5)->count() > 0)
                                @foreach(auth()->user()->notifications->take(5) as $notification)
                                    <li>
                                        <a class="dropdown-item {{ $notification->read_at ? '' : 'fw-bold' }}"
                                           href="{{ $notification->data['url'] ?? '#' }}">
                                            {{ Str::limit($notification->data['message'] ?? 'Новое уведомление', 50) }}
                                            <br>
                                            <small class="text-muted">
                                                {{ $notification->created_at->diffForHumans() }}
                                            </small>
                                        </a>
                                    </li>
                                @endforeach
                                <li><hr class="dropdown-divider"></li>
                            @else
                                <li><span class="dropdown-item text-muted">Нет уведомлений</span></li>
                            @endif
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.notifications') }}">
                                    <i class="fas fa-list me-2"></i>Все уведомления
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Профиль пользователя -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                           data-bs-toggle="dropdown">
                            @if(auth()->user()->avatar)
                                <img src="{{ asset('storage/' . auth()->user()->avatar) }}"
                                     alt="{{ auth()->user()->name }}"
                                     class="rounded-circle" width="30" height="30">
                            @else
                                <i class="fas fa-user-circle"></i>
                            @endif
                            <span class="ms-2 d-none d-lg-inline">
                                {{ Str::limit(auth()->user()->name, 15) }}
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if(auth()->user()->isAdmin())
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                        <i class="fas fa-cog me-2"></i>Админ-панель
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.index') }}">
                                    <i class="fas fa-user me-2"></i>Личный кабинет
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.bookings') }}">
                                    <i class="fas fa-calendar-check me-2"></i>Мои бронирования
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="fas fa-edit me-2"></i>Редактировать профиль
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('chat.index') }}">
                                    <i class="fas fa-comments me-2"></i>Чат с поддержкой
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i>Выйти
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>

                    <!-- Кнопка бронирования -->
                    <li class="nav-item ms-2 d-none d-lg-block">
                        <a href="{{ route('booking.step1') }}" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-1"></i>Забронировать
                        </a>
                    </li>
                @else
                    <!-- Гость -->
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}"
                           href="{{ route('login') }}">
                            <i class="fas fa-sign-in-alt me-1"></i>Войти
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('register') ? 'active' : '' }}"
                           href="{{ route('register') }}">
                            <i class="fas fa-user-plus me-1"></i>Регистрация
                        </a>
                    </li>
                    <li class="nav-item ms-2 d-none d-lg-block">
                        <a href="{{ route('booking.step1') }}" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-1"></i>Забронировать
                        </a>
                    </li>
                @endauth

                <!-- Мобильный поиск -->
                <li class="nav-item d-lg-none mt-2">
                    <form class="d-flex" action="{{ route('search.rooms') }}" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control"
                                   placeholder="Поиск номеров..." name="query">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show m-0 rounded-0" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show m-0 rounded-0" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show m-0 rounded-0" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
