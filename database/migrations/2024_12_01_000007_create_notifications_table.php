<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notification_number')->unique()->nullable(); // Уникальный номер уведомления

            // Получатель
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade');

            // Тип и категория уведомления
            $table->enum('type', [
                'system',       // Системное
                'booking',      // Бронирование
                'payment',      // Платеж
                'reminder',     // Напоминание
                'promotion',    // Акция/скидка
                'support',      // Поддержка
                'review',       // Отзыв
                'admin',        // Административное
                'other',        // Другое
            ]);

            $table->enum('category', [
                'info',         // Информационное
                'success',      // Успех
                'warning',      // Предупреждение
                'error',        // Ошибка
                'important',    // Важное
            ])->default('info');

            // Содержание уведомления
            $table->string('subject'); // Заголовок
            $table->text('message');   // Текст уведомления
            $table->json('data')->nullable(); // Дополнительные данные (JSON)

            // Каналы отправки
            $table->boolean('via_site')->default(true); // Уведомление на сайте
            $table->boolean('via_email')->default(false); // Email уведомление
            $table->boolean('via_sms')->default(false); // SMS уведомление
            $table->boolean('via_telegram')->default(false); // Telegram уведомление
            $table->boolean('via_push')->default(false); // Push уведомление

            // Статус отправки
            $table->enum('status', [
                'pending',      // Ожидает отправки
                'sent',         // Отправлено
                'delivered',    // Доставлено
                'read',         // Прочитано
                'failed',       // Ошибка отправки
                'cancelled',    // Отменено
            ])->default('pending');

            // Время отправки/чтения
            $table->timestamp('scheduled_at')->nullable(); // Когда отправить
            $table->timestamp('sent_at')->nullable(); // Когда отправлено
            $table->timestamp('delivered_at')->nullable(); // Когда доставлено
            $table->timestamp('read_at')->nullable(); // Когда прочитано

            // Результаты отправки
            $table->json('delivery_report')->nullable(); // Отчет о доставке (JSON)
            $table->text('error_message')->nullable(); // Сообщение об ошибке

            // Флаги
            $table->boolean('is_important')->default(false); // Важное уведомление
            $table->boolean('requires_action')->default(false); // Требует действия
            $table->boolean('is_broadcast')->default(false); // Массовая рассылка

            // Ссылка на действие
            $table->string('action_url')->nullable(); // URL для действия
            $table->string('action_text')->nullable(); // Текст кнопки действия

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы для поиска
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('scheduled_at');
            $table->index('is_important');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
