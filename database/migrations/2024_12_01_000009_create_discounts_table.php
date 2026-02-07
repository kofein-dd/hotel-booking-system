<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название скидки/акции
            $table->string('code')->unique()->nullable(); // Промокод (если есть)
            $table->enum('type', [
                'percentage',   // Процентная скидка
                'fixed',        // Фиксированная сумма
                'free_night',   // Бесплатная ночь
                'upgrade',      // Улучшение номера
            ]);

            $table->decimal('value', 10, 2); // Значение скидки
            $table->string('currency')->default('USD'); // Для фиксированных скидок

            // Ограничения применения
            $table->enum('applicable_to', [
                'all',          // На все
                'room_type',    // На тип номера
                'specific_room',// На конкретный номер
                'booking_duration', // На продолжительность брони
                'seasonal',     // Сезонная
                'first_booking',// Первое бронирование
            ])->default('all');

            $table->json('applicable_values')->nullable(); // Значения для ограничений (JSON)

            // Условия
            $table->decimal('minimum_booking_amount', 10, 2)->nullable(); // Мин. сумма брони
            $table->integer('minimum_nights')->nullable(); // Мин. количество ночей
            $table->integer('maximum_nights')->nullable(); // Макс. количество ночей
            $table->integer('maximum_guests')->nullable(); // Макс. количество гостей

            // Даты действия
            $table->date('valid_from')->nullable(); // Дата начала действия
            $table->date('valid_to')->nullable();   // Дата окончания действия

            // Лимиты использования
            $table->integer('usage_limit')->nullable();      // Общий лимит использований
            $table->integer('usage_limit_per_user')->nullable(); // Лимит на пользователя
            $table->integer('used_count')->default(0);       // Количество использований

            // Статус
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true); // Виден пользователям
            $table->boolean('is_auto_apply')->default(false); // Автоматически применяется

            // Приоритет
            $table->integer('priority')->default(0); // Приоритет применения (чем выше, тем раньше)

            // Описание
            $table->text('description')->nullable();
            $table->text('terms')->nullable(); // Условия использования

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы для поиска
            $table->index(['code', 'is_active']);
            $table->index(['valid_from', 'valid_to']);
            $table->index(['type', 'is_active']);
            $table->index('priority');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
