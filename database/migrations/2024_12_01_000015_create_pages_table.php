<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Заголовок страницы
            $table->string('slug')->unique(); // URL-адрес
            $table->text('content')->nullable(); // Содержимое (HTML)
            $table->text('excerpt')->nullable(); // Краткое описание
            $table->string('meta_title')->nullable(); // Meta title
            $table->text('meta_description')->nullable(); // Meta description
            $table->json('meta_keywords')->nullable(); // Meta keywords

            // Тип страницы
            $table->enum('type', [
                'page',        // Обычная страница
                'home',        // Главная страница
                'contact',     // Контакты
                'about',       // О нас
                'terms',       // Условия использования
                'privacy',     // Политика конфиденциальности
                'custom',      // Пользовательская
            ])->default('page');

            // Статус
            $table->enum('status', [
                'published',   // Опубликовано
                'draft',       // Черновик
                'scheduled',   // Запланировано
                'private',     // Приватная
                'archived',    // В архиве
            ])->default('draft');

            // Даты
            $table->timestamp('published_at')->nullable(); // Дата публикации
            $table->timestamp('scheduled_at')->nullable(); // Запланированная дата

            // Изображение/обложка
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable(); // Галерея изображений

            // Шаблон
            $table->string('template')->nullable(); // Пользовательский шаблон

            // Порядок и родительская страница
            $table->integer('parent_id')->nullable()->index(); // Родительская страница
            $table->integer('order')->default(0); // Порядок сортировки

            // SEO и доступность
            $table->boolean('is_indexable')->default(true); // Индексировать поисковиками
            $table->boolean('is_searchable')->default(true); // Доступна для поиска
            $table->boolean('show_in_menu')->default(true); // Показывать в меню
            $table->boolean('show_in_footer')->default(false); // Показывать в футере

            // Доступ
            $table->enum('access_level', [
                'public',      // Публичный доступ
                'registered',  // Только зарегистрированным
                'private',     // Приватный доступ
            ])->default('public');

            // Автор
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы
            $table->index(['status', 'published_at']);
            $table->index(['type', 'status']);
            $table->index('slug');
            $table->index('order');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
