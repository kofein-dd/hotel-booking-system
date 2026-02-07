<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ban_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('banned_by')->constrained('users')->onDelete('cascade');

            // Тип и причина бана
            $table->enum('type', [
                'temporary',   // Временный бан
                'permanent',   // Постоянный бан
                'warning',     // Предупреждение
                'shadow',      // Скрытый бан (ограничения без уведомления)
            ])->default('temporary');

            $table->enum('reason', [
                'spam',               // Спам
                'abuse',              // Оскорбления
                'fraud',              // Мошенничество
                'multiple_accounts',  // Множественные аккаунты
                'chargeback',         // Возврат платежа
                'policy_violation',   // Нарушение правил
                'inappropriate_content', // Неподобающий контент
                'other',              // Другое
            ])->default('other');

            $table->text('reason_description')->nullable(); // Подробное описание причины
            $table->json('evidence')->nullable(); // Доказательства (скриншоты, логи)

            // Срок бана
            $table->timestamp('banned_at')->useCurrent();
            $table->timestamp('banned_until')->nullable(); // До какой даты (для временного бана)

            // Авторазбан
            $table->boolean('auto_unban')->default(true); // Автоматический разбан по истечении срока
            $table->timestamp('unbanned_at')->nullable(); // Дата разбана
            $table->foreignId('unbanned_by')->nullable()->constrained('users')->onDelete('set null');

            // Ограничения
            $table->boolean('restrict_booking')->default(true); // Запрет бронирования
            $table->boolean('restrict_messaging')->default(false); // Запрет сообщений
            $table->boolean('restrict_reviews')->default(false); // Запрет отзывов
            $table->json('restrictions')->nullable(); // Дополнительные ограничения (JSON)

            // Статус
            $table->boolean('is_active')->default(true); // Активен ли бан

            // История
            $table->integer('warning_count')->default(0); // Количество предупреждений
            $table->json('ban_history')->nullable(); // История предыдущих банов (JSON)

            // IP и техническая информация
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('technical_data')->nullable(); // Технические данные (JSON)

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы для поиска
            $table->index(['user_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index('banned_until');
            $table->index('banned_at');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ban_lists');
    }
};
