<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Логотип -->
    <a href="{{ route('admin.dashboard') }}" class="brand-link">
        <img src="{{ asset('img/admin-logo.png') }}" alt="Admin Logo"
             class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">{{ config('app.name') }}</span>
    </a>

    <!-- Сайдбар -->
    <div class="sidebar">
        <!-- Информация о пользователе -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="{{ auth()->user()->avatar_url ?? asset('img/default-avatar.png') }}"
                     class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="{{ route('profile.index') }}" class="d-block">{{ auth()->user()->name }}</a>
                <small class="text-success">
                    <i class="fas fa-circle fa-xs"></i> Онлайн
                </small>
            </div>
        </div>

        <!-- Поиск (опционально) -->
        <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search"
                       placeholder="Поиск..." aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Меню навигации -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Главная -->
                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Главная</p>
                    </a>
                </li>

                <!-- Бронирования -->
                <li class="nav-item {{ request()->routeIs('admin.bookings.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-calendar-check"></i>
                        <p>
                            Бронирования
                            <i class="right fas fa-angle-left"></i>
                            @if($pendingBookings = \App\Models\Booking::where('status', 'pending')->count())
                                <span class="badge badge-info right">{{ $pendingBookings }}</span>
                            @endif
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.bookings.index') }}"
                               class="nav-link {{ request()->routeIs('admin.bookings.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Все бронирования</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}"
                               class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Ожидают подтверждения</p>
                                @if($pendingBookings)
                                    <span class="badge badge-warning float-right">{{ $pendingBookings }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.bookings.index', ['status' => 'confirmed']) }}"
                               class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Подтвержденные</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Пользователи -->
                <li class="nav-item {{ request()->routeIs('admin.users.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Пользователи
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}"
                               class="nav-link {{ request()->routeIs('admin.users.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Все пользователи</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.ban-list.index') }}"
                               class="nav-link {{ request()->routeIs('admin.ban-list.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Бан-лист</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Номера -->
                <li class="nav-item {{ request()->routeIs('admin.rooms.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-bed"></i>
                        <p>
                            Номера
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.rooms.index') }}"
                               class="nav-link {{ request()->routeIs('admin.rooms.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Все номера</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.rooms.create') }}"
                               class="nav-link {{ request()->routeIs('admin.rooms.create') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Добавить номер</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Платежи -->
                <li class="nav-item">
                    <a href="{{ route('admin.payments.index') }}"
                       class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <p>Платежи</p>
                    </a>
                </li>

                <!-- Отзывы -->
                <li class="nav-item {{ request()->routeIs('admin.reviews.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-star"></i>
                        <p>
                            Отзывы
                            <i class="right fas fa-angle-left"></i>
                            @if($pendingReviews = \App\Models\ReviewReport::where('status', 'pending')->count())
                                <span class="badge badge-danger right">{{ $pendingReviews }}</span>
                            @endif
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.reviews.index') }}"
                               class="nav-link {{ request()->routeIs('admin.reviews.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Все отзывы</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.review-reports.index') }}"
                               class="nav-link {{ request()->routeIs('admin.review-reports.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Жалобы на отзывы</p>
                                @if($pendingReviews)
                                    <span class="badge badge-danger float-right">{{ $pendingReviews }}</span>
                                @endif
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Скидки и акции -->
                <li class="nav-item">
                    <a href="{{ route('admin.discounts.index') }}"
                       class="nav-link {{ request()->routeIs('admin.discounts.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-percent"></i>
                        <p>Скидки и акции</p>
                    </a>
                </li>

                <!-- Чат -->
                <li class="nav-item">
                    <a href="{{ route('admin.chat.index') }}"
                       class="nav-link {{ request()->routeIs('admin.chat.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-comments"></i>
                        <p>
                            Чат
                            @if($unreadChats = \App\Models\ChatMessage::where('read_at', null)->where('is_admin_message', false)->count())
                                <span class="badge badge-danger right">{{ $unreadChats }}</span>
                            @endif
                        </p>
                    </a>
                </li>

                <!-- Уведомления -->
                <li class="nav-item">
                    <a href="{{ route('admin.notifications.index') }}"
                       class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-bell"></i>
                        <p>Уведомления</p>
                    </a>
                </li>

                <!-- Контент -->
                <li class="nav-item {{ request()->routeIs('admin.pages.*') || request()->routeIs('admin.faqs.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-edit"></i>
                        <p>
                            Контент
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.pages.index') }}"
                               class="nav-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Страницы</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.faqs.index') }}"
                               class="nav-link {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>FAQ</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Статистика -->
                <li class="nav-item">
                    <a href="{{ route('admin.statistics.index') }}"
                       class="nav-link {{ request()->routeIs('admin.statistics.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Статистика</p>
                    </a>
                </li>

                <!-- Система -->
                <li class="nav-item {{ request()->routeIs('admin.settings.*') || request()->routeIs('admin.audit-logs.*') || request()->routeIs('admin.backups.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>
                            Система
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.settings.index') }}"
                               class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Настройки</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.audit-logs.index') }}"
                               class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Журнал аудита</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.backups.index') }}"
                               class="nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Резервные копии</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</aside>
