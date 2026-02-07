<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Кто пожаловался

            // Причина жалобы
            $table->enum('reason', [
                'spam',               // Спам
                'inappropriate',      // Неподобающий контент
                'false_information',  // Ложная информация
                'harassment',         // Оскорбления/домогательства
                'conflict_of_interest', // Конфликт интересов
                'fake_review',        // Поддельный отзыв
                'other',              // Другое
            ]);

            $table->text('description')->nullable(); // Подробное описание
            $table->json('evidence')->nullable(); // Доказательства (скриншоты и т.д.)

            // Статус жалобы
            $table->enum('status', [
                'pending',      // Ожидает рассмотрения
                'investigating',// В процессе расследования
                'resolved',     // Решено
                'dismissed',    // Отклонено
                'duplicate',    // Дубликат
            ])->default('pending');

            // Решение
            $table->enum('resolution', [
                'review_hidden',      // Отзыв скрыт
                'review_edited',      // Отзыв отредактирован
                'user_warned',        // Пользователь предупрежден
                'user_banned',        // Пользователь забанен
                'no_action',          // Действий не требуется
                'pending',            // Ожидает решения
            ])->nullable();

            $table->text('resolution_notes')->nullable(); // Примечания к решению
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();

            // Приоритет
            $table->integer('priority')->default(1); // 1-5, где 5 - наивысший

            // Теги
            $table->json('tags')->nullable();

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы
            $table->index(['review_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'priority']);
            $table->index('resolved_at');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
