<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row">
            <!-- О компании -->
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="fw-bold mb-3">Отель у Моря</h5>
                <p class="text-light">
                    Комфортабельный отель на берегу моря с современными номерами,
                    рестораном и СПА-центром. Идеальное место для отдыха и релаксации.
                </p>
                <div class="social-icons mt-3">
                    <a href="https://vk.com/hotelbysea" class="text-white me-3" target="_blank" title="ВКонтакте">
                        <i class="fab fa-vk fa-lg"></i>
                    </a>
                    <a href="https://t.me/hotelbysea" class="text-white me-3" target="_blank" title="Telegram">
                        <i class="fab fa-telegram fa-lg"></i>
                    </a>
                    <a href="https://www.instagram.com/hotelbysea" class="text-white me-3" target="_blank" title="Instagram">
                        <i class="fab fa-instagram fa-lg"></i>
                    </a>
                    <a href="https://www.facebook.com/hotelbysea" class="text-white" target="_blank" title="Facebook">
                        <i class="fab fa-facebook fa-lg"></i>
                    </a>
                </div>
            </div>

            <!-- Быстрые ссылки -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h5 class="fw-bold mb-3">Быстрые ссылки</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="{{ route('home') }}" class="text-light text-decoration-none">
                            <i class="fas fa-home me-1"></i> Главная
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('rooms.index') }}" class="text-light text-decoration-none">
                            <i class="fas fa-bed me-1"></i> Номера
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('hotel.about') }}" class="text-light text-decoration-none">
                            <i class="fas fa-info-circle me-1"></i> Об отеле
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('hotel.gallery') }}" class="text-light text-decoration-none">
                            <i class="fas fa-images me-1"></i> Галерея
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('faq.index') }}" class="text-light text-decoration-none">
                            <i class="fas fa-question-circle me-1"></i> FAQ
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Контакты -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="fw-bold mb-3">Контакты</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <span class="text-light">Краснодарский край, г. Сочи, ул. Приморская, 123</span>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-phone me-2"></i>
                        <a href="tel:+78621234567" class="text-light text-decoration-none">
                            +7 (862) 123-45-67
                        </a>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-envelope me-2"></i>
                        <a href="mailto:info@hotel-by-sea.ru" class="text-light text-decoration-none">
                            info@hotel-by-sea.ru
                        </a>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-clock me-2"></i>
                        <span class="text-light">Круглосуточно</span>
                    </li>
                </ul>
                <a href="{{ route('contact.index') }}" class="btn btn-outline-light btn-sm mt-2">
                    <i class="fas fa-envelope me-1"></i> Написать нам
                </a>
            </div>

            <!-- Полезные страницы -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="fw-bold mb-3">Полезные страницы</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'terms') }}" class="text-light text-decoration-none">
                            <i class="fas fa-file-contract me-1"></i> Условия бронирования
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'privacy') }}" class="text-light text-decoration-none">
                            <i class="fas fa-shield-alt me-1"></i> Политика конфиденциальности
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'cancellation') }}" class="text-light text-decoration-none">
                            <i class="fas fa-ban me-1"></i> Политика отмены
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('pages.show', 'payment') }}" class="text-light text-decoration-none">
                            <i class="fas fa-credit-card me-1"></i> Способы оплаты
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('sitemap') }}" class="text-light text-decoration-none">
                            <i class="fas fa-sitemap me-1"></i> Карта сайта
                        </a>
                    </li>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <li class="mb-2">
                                <a href="{{ route('admin.dashboard') }}" class="text-warning text-decoration-none">
                                    <i class="fas fa-cog me-1"></i> Админ-панель
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>
            </div>
        </div>

        <hr class="bg-light">

        <div class="row">
            <div class="col-md-6">
                <p class="mb-0 text-light">
                    &copy; {{ date('Y') }} Отель у Моря. Все права защищены.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0 text-light">
                    Разработано с <i class="fas fa-heart text-danger"></i> для вашего комфорта
                </p>
            </div>
        </div>

        <!-- Кнопка "Наверх" -->
        <div class="position-fixed bottom-0 end-0 mb-4 me-4">
            <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="btn btn-primary btn-lg rounded-circle shadow"
                    title="Наверх">
                <i class="fas fa-arrow-up"></i>
            </button>
        </div>
    </div>
</footer>

<!-- Модальное окно для быстрого бронирования -->
@auth
    <div class="modal fade" id="quickBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Быстрое бронирование</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Перейти к выбору дат для бронирования номера</p>
                    <a href="{{ route('booking.step1') }}" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-1"></i> Начать бронирование
                    </a>
                </div>
            </div>
        </div>
    </div>
@endauth

<!-- Скрипты -->
<script>
    // Анимация скролла к верху
    document.addEventListener('DOMContentLoaded', function() {
        const scrollToTopBtn = document.querySelector('[title="Наверх"]');

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollToTopBtn.style.display = 'block';
            } else {
                scrollToTopBtn.style.display = 'none';
            }
        });

        // Инициализация всплывающих подсказок
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
