<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Админ-панель') - {{ config('app.name') }}</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            position: fixed;
            width: 250px;
            padding-top: 20px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 20px;
            margin: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .navbar-top {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            padding: 10px 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-confirmed { background-color: #d4edda; color: #155724; }
        .badge-cancelled { background-color: #f8d7da; color: #721c24; }
        .badge-completed { background-color: #d1ecf1; color: #0c5460; }
    </style>

    @stack('styles')
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <div class="text-center mb-4">
        <h4 class="mb-0">{{ config('app.name') }}</h4>
        <small>Админ-панель</small>
    </div>

    <nav class="nav flex-column">
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-tachometer-alt"></i> Дашборд
        </a>

        <hr class="bg-white mx-3">

        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
            <i class="fas fa-users"></i> Пользователи
        </a>

        <a class="nav-link {{ request()->routeIs('admin.hotels.*') ? 'active' : '' }}" href="{{ route('admin.hotels.index') }}">
            <i class="fas fa-hotel"></i> Отель
        </a>

        <a class="nav-link {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}" href="{{ route('admin.rooms.index') }}">
            <i class="fas fa-bed"></i> Номера
        </a>

        <a class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}">
            <i class="fas fa-calendar-check"></i> Бронирования
        </a>

        <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">
            <i class="fas fa-credit-card"></i> Платежи
        </a>

        <a class="nav-link {{ request()->routeIs('admin.discounts.*') ? 'active' : '' }}" href="{{ route('admin.discounts.index') }}">
            <i class="fas fa-tags"></i> Скидки
        </a>

        <a class="nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}" href="{{ route('admin.reviews.index') }}">
            <i class="fas fa-star"></i> Отзывы
        </a>

        <a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">
            <i class="fas fa-bell"></i> Уведомления
        </a>

        <a class="nav-link {{ request()->routeIs('admin.chat.*') ? 'active' : '' }}" href="{{ route('admin.chat.index') }}">
            <i class="fas fa-comments"></i> Чат
        </a>

        <a class="nav-link {{ request()->routeIs('admin.statistics.*') ? 'active' : '' }}" href="{{ route('admin.statistics.index') }}">
            <i class="fas fa-chart-bar"></i> Статистика
        </a>

        <hr class="bg-white mx-3 mt-auto">

        <a class="nav-link" href="{{ route('home') }}" target="_blank">
            <i class="fas fa-external-link-alt"></i> На сайт
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link text-start w-100 border-0 bg-transparent">
                <i class="fas fa-sign-out-alt"></i> Выйти
            </button>
        </form>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <nav class="navbar-top mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">@yield('page-title')</h5>

            <div class="d-flex align-items-center">
                <span class="me-3">Привет, {{ Auth::user()->name }}</span>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fas fa-user me-2"></i>Профиль</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Выйти</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // Auto-dismiss alerts after 5 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });
</script>

@stack('scripts')
</body>
</html>
