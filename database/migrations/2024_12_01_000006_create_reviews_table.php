<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');

            // Рейтинги (1-5)
            $table->tinyInteger('rating_overall')->unsigned(); // Общий рейтинг
            $table->tinyInteger('rating_cleanliness')->unsigned()->nullable();
            $table->tinyInteger('rating_comfort')->unsigned()->nullable();
            $table->tinyInteger('rating_location')->unsigned()->nullable();
            $table->tinyInteger('rating_service')->unsigned()->nullable();
            $table->tinyInteger('rating_value')->unsigned()->nullable();

            // Текст отзыва
            $table->string('title'); // Заголовок отзыва
            $table->text('comment'); // Текст отзыва
            $table->text('pros')->nullable(); // Плюсы
            $table->text('cons')->nullable(); // Минусы

            // Медиа
            $table->json('photos')->nullable(); // Фото, приложенные к отзыву
            $table->json('videos')->nullable(); // Видео отзыва

            // Статус модерации
            $table->enum('status', [
                'pending',   // На модерации
                'approved',  // Одобрено
                'rejected',  // Отклонено
                'hidden',    // Скрыто
            ])->default('pending');

            // Ответ отеля
            $table->text('hotel_reply')->nullable();
            $table->timestamp('hotel_reply_at')->nullable();
            $table->foreignId('hotel_reply_by')->nullable()->constrained('users')->onDelete('set null');

            // Полезность отзыва
            $table->integer('helpful_count')->default(0);
            $table->integer('unhelpful_count')->default(0);

            // Мета-данные
            $table->json('metadata')->nullable();

            // Индексы для поиска
            $table->index(['room_id', 'status']);
            $table->index(['hotel_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('rating_overall');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
