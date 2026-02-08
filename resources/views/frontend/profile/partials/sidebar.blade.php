<div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
    <!-- Информация пользователя -->
    <div class="card-body text-center p-4">
        <div class="avatar avatar-xl bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
            @if(Auth::user()->avatar_url)
                <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}"
                     class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
            @else
                <span class="fs-2">{{ substr(Auth::user()->name, 0, 1) }}</span>
            @endif
        </div>
        <h6 class="fw-bold mb-1">{{ Auth::user()->name }}</h6>
        <p class="text-muted small mb-3">
            <i class="bi bi-person-badge me-1"></i>
            {{ Auth::user()->isPremium() ? 'Премиум' : 'Стандартный' }} аккаунт
        </p>

        @if(Auth::user()->hasActiveDiscount())
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                <i class="bi bi-percent me-1"></i>
                <small>У вас есть скидка {{ Auth::user()->activeDiscount()->value }}%</small>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif
    </div>

    <!-- Навигация -->
    <div class="list-group list-group-flush">
        <a href="{{ route('profile.index') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.index') ? 'active' : '' }}">
            <i class="bi bi-house-door me-3"></i>Главная
        </a>

        <a href="{{ route('profile.bookings') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.bookings*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check me-3"></i>Мои бронирования
            @if($activeBookings > 0)
                <span class="badge bg-primary float-end">{{ $activeBookings }}</span>
            @endif
        </a>

        <a href="{{ route('profile.reviews') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.reviews*') ? 'active' : '' }}">
            <i class="bi bi-star me-3"></i>Мои отзывы
            @if($totalReviews > 0)
                <span class="badge bg-success float-end">{{ $totalReviews }}</span>
            @endif
        </a>

        <a href="{{ route('profile.notifications') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.notifications*') ? 'active' : '' }}">
            <i class="bi bi-bell me-3"></i>Уведомления
            @if($unreadNotifications > 0)
                <span class="badge bg-danger float-end" data-notification-count>{{ $unreadNotifications }}</span>
            @endif
        </a>

        <a href="{{ route('profile.chat.index') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.chat*') ? 'active' : '' }}">
            <i class="bi bi-chat-dots me-3"></i>Чат с поддержкой
            @if($unreadMessages > 0)
                <span class="badge bg-warning float-end">{{ $unreadMessages }}</span>
            @endif
        </a>

        <div class="dropdown-divider my-1"></div>

        <a href="{{ route('profile.edit') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
            <i class="bi bi-person-circle me-3"></i>Настройки профиля
        </a>

        <a href="{{ route('profile.security') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.security*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock me-3"></i>Безопасность
        </a>

        <a href="{{ route('profile.settings') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.settings') ? 'active' : '' }}">
            <i class="bi bi-gear me-3"></i>Настройки уведомлений
        </a>

        <div class="dropdown-divider my-1"></div>

        <a href="{{ route('profile.payments') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.payments*') ? 'active' : '' }}">
            <i class="bi bi-credit-card me-3"></i>История платежей
        </a>

        <a href="{{ route('profile.discounts') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.discounts*') ? 'active' : '' }}">
            <i class="bi bi-percent me-3"></i>Мои скидки
        </a>

        <div class="dropdown-divider my-1"></div>

        <a href="{{ route('profile.favorites') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.favorites*') ? 'active' : '' }}">
            <i class="bi bi-heart me-3"></i>Избранное
        </a>

        <a href="{{ route('profile.history') }}"
           class="list-group-item list-group-item-action border-0 py-3 {{ request()->routeIs('profile.history*') ? 'active' : '' }}">
            <i class="bi bi-clock-history me-3"></i>История посещений
        </a>
    </div>

    <!-- Кнопка выхода -->
    <div class="card-footer bg-white border-0 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-danger w-100">
                <i class="bi bi-box-arrow-right me-2"></i>Выйти
            </button>
        </form>
    </div>

    <!-- Информация о системе -->
    <div class="card-footer bg-light border-0 p-3">
        <div class="text-center small text-muted">
            <div class="mb-1">
                <i class="bi bi-shield-check text-success me-1"></i>
                Аккаунт защищен
            </div>
            <div>
                <i class="bi bi-info-circle me-1"></i>
                Последний вход: {{ Auth::user()->last_login_at ? Auth::user()->last_login_at->diffForHumans() : 'Недавно' }}
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .list-group-item {
            border-radius: 8px !important;
            margin-bottom: 2px;
            font-weight: 500;
        }

        .list-group-item.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .list-group-item:hover:not(.active) {
            background-color: #f8f9fa;
        }

        .badge {
            min-width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .sticky-top {
            z-index: 100;
        }

        .btn-close-sm {
            padding: 0.25rem;
            font-size: 0.75rem;
        }

        @media (max-width: 991px) {
            .sticky-top {
                position: static !important;
            }
        }
    </style>
@endpush
