<footer class="footer bg-dark text-white pt-5 pb-4">
    <div class="container">
        <div class="row">
            <!-- О компании -->
            <div class="col-lg-4 mb-4">
                <h5 class="fw-bold mb-4">
                    <i class="bi bi-house-heart text-primary"></i> {{ config('app.name', 'Отель у моря') }}
                </h5>
                <p class="text-muted mb-4">
                    {{ $hotel->short_description ?? 'Роскошный отель для незабываемого отдыха у самого синего моря. Комфорт, уют и безупречный сервис.' }}
                </p>
                <div class="d-flex gap-3">
                    <a href="{{ $hotel->facebook_url ?? '#' }}" class="text-white fs-5" target="_blank">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="{{ $hotel->instagram_url ?? '#' }}" class="text-white fs-5" target="_blank">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="{{ $hotel->telegram_url ?? '#' }}" class="text-white fs-5" target="_blank">
                        <i class="bi bi-telegram"></i>
                    </a>
                    <a href="{{ $hotel->vk_url ?? '#' }}" class="text-white fs-5" target="_blank">
                        <i class="bi bi-vimeo"></i>
                    </a>
                </div>
            </div>

            <!-- Быстрые ссылки -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-4">Отель</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'about') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Об отеле
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('rooms.index') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Номера и цены
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'services') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Услуги
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'gallery') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Галерея
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'contacts') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Контакты
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Для гостей -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-4">Гостям</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="{{ route('booking.step1') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Онлайн бронирование
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('faqs.index') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Частые вопросы
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'reviews') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Отзывы
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'special-offers') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Спецпредложения
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'how-to-get') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Как добраться
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Контакты -->
            <div class="col-lg-4 mb-4">
                <h6 class="fw-bold mb-4">Контакты</h6>
                <ul class="list-unstyled">
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-geo-alt text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Адрес:</strong>
                            <p class="mb-0 text-white-50">{{ $hotel->address ?? 'ул. Морская, 123, г. Сочи, Краснодарский край, Россия' }}</p>
                        </div>
                    </li>
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-telephone text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Телефон:</strong>
                            <p class="mb-0">
                                <a href="tel:{{ $hotel->phone ?? '+78005553535' }}" class="text-white-50 text-decoration-none hover-text-white">
                                    {{ $hotel->phone ?? '+7 (800) 555-35-35' }}
                                </a>
                            </p>
                        </div>
                    </li>
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-envelope text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Email:</strong>
                            <p class="mb-0">
                                <a href="mailto:{{ $hotel->email ?? 'info@hotel.ru' }}" class="text-white-50 text-decoration-none hover-text-white">
                                    {{ $hotel->email ?? 'info@hotel.ru' }}
                                </a>
                            </p>
                        </div>
                    </li>
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-clock text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Ресепшн:</strong>
                            <p class="mb-0 text-white-50">Круглосуточно</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="bg-white-50 my-4">

        <!-- Нижняя часть футера -->
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0 text-white-50">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Отель у моря') }}. Все права защищены.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item me-3">
                        <a href="{{ route('pages.show', 'privacy-policy') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Политика конфиденциальности
                        </a>
                    </li>
                    <li class="list-inline-item me-3">
                        <a href="{{ route('pages.show', 'terms-of-use') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Условия использования
                        </a>
                    </li>
                    <li class="list-inline-item">
                        <a href="{{ route('sitemap.index') }}" class="text-white-50 text-decoration-none hover-text-white">
                            Карта сайта
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Платежные системы -->
        <div class="row mt-4">
            <div class="col">
                <h6 class="fw-bold mb-3 text-white-50">Мы принимаем:</h6>
                <div class="d-flex flex-wrap gap-3">
                    <div class="payment-icon">
                        <i class="bi bi-credit-card-2-front fs-3 text-white-50"></i>
                        <span class="visually-hidden">Visa</span>
                    </div>
                    <div class="payment-icon">
                        <i class="bi bi-credit-card fs-3 text-white-50"></i>
                        <span class="visually-hidden">Mastercard</span>
                    </div>
                    <div class="payment-icon">
                        <i class="bi bi-credit-card-fill fs-3 text-white-50"></i>
                        <span class="visually-hidden">МИР</span>
                    </div>
                    <div class="payment-icon">
                        <i class="bi bi-cash fs-3 text-white-50"></i>
                        <span class="visually-hidden">Наличные</span>
                    </div>
                    <div class="payment-icon">
                        <i class="bi bi-phone fs-3 text-white-50"></i>
                        <span class="visually-hidden">Мобильные платежи</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Кнопка "Наверх" -->
    <button class="btn btn-primary btn-back-to-top" id="backToTop">
        <i class="bi bi-chevron-up"></i>
    </button>
</footer>

@push('styles')
    <style>
        .footer {
            position: relative;
            margin-top: auto;
        }

        .footer a.hover-text-white:hover {
            color: #fff !important;
            text-decoration: underline !important;
        }

        .payment-icon {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 45px;
            transition: all 0.3s;
        }

        .payment-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        .btn-back-to-top.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .footer {
                text-align: center;
            }

            .footer ul li {
                justify-content: center !important;
            }

            .payment-icon {
                width: 50px;
                height: 40px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Кнопка "Наверх"
            const backToTopButton = document.getElementById('backToTop');

            if (backToTopButton) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        backToTopButton.classList.add('show');
                    } else {
                        backToTopButton.classList.remove('show');
                    }
                });

                backToTopButton.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // Год в копирайте
            const currentYear = new Date().getFullYear();
            document.querySelectorAll('.current-year').forEach(element => {
                element.textContent = currentYear;
            });
        });
    </script>
@endpush
