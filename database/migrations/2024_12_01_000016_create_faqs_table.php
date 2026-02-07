<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question'); // Вопрос
            $table->text('answer'); // Ответ
            $table->text('short_answer')->nullable(); // Краткий ответ

            // Категория
            $table->string('category')->default('general')->index();
            $table->integer('category_order')->default(0); // Порядок в категории

            // Приоритет и видимость
            $table->integer('priority')->default(0); // Приоритет (чем выше, тем важнее)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // Избранный FAQ
            $table->boolean('show_on_homepage')->default(false); // Показывать на главной

            // Статистика
            $table->integer('views')->default(0); // Количество просмотров
            $table->integer('helpful_count')->default(0); // Полезно
            $table->integer('unhelpful_count')->default(0); // Не полезно

            // Теги
            $table->json('tags')->nullable();

            // Автор и редактор
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->onDelete('set null');

            // Даты
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_edited_at')->nullable();

            // Связи
            $table->foreignId('related_faq_id')->nullable()->constrained('faqs')->onDelete('set null');

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы
            $table->index(['category', 'priority']);
            $table->index(['is_active', 'is_featured']);
            $table->index('published_at');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
