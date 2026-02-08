<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Отель у моря'))</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Guest CSS -->
    <link href="{{ asset('css/guest.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">
<!-- Простой навбар для гостевых страниц -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <i class="bi bi-house-heart-fill text-primary fs-4"></i>
            <span class="fw-bold ms-2">{{ config('app.name', 'Отель у моря') }}</span>
        </a>

        <div class="d-flex align-items-center">
            <a href="{{ route('home') }}" class="btn btn-outline-primary me-2">
                <i class="bi bi-house-door"></i> На главную
            </a>
            @if(Route::currentRouteName() != 'login')
                <a href="{{ route('login') }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Войти
                </a>
            @endif
        </div>
    </div>
</nav>

<!-- Основной контент -->
<main class="flex-grow-1 py-4">
    @yield('content')
</main>

<!-- Простой футер -->
<footer class="bg-light border-top py-4 mt-auto">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5 class="fw-bold">
                    <i class="bi bi-house-heart text-primary"></i> {{ config('app.name') }}
                </h5>
                <p class="text-muted small">
                    Система онлайн-бронирования отеля у моря
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1">
                    <a href="{{ route('pages.show', 'terms') }}" class="text-decoration-none me-3">
                        Условия использования
                    </a>
                    <a href="{{ route('pages.show', 'privacy') }}" class="text-decoration-none">
                        Политика конфиденциальности
                    </a>
                </p>
                <p class="text-muted small mb-0">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Все права защищены.
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
