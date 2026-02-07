<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('backup_number')->unique(); // Уникальный номер бекапа

            // Тип и цель
            $table->enum('type', [
                'full',         // Полный бекап
                'database',     // Только база данных
                'files',        // Только файлы
                'incremental',  // Инкрементальный
            ]);

            $table->enum('purpose', [
                'scheduled',    // По расписанию
                'manual',       // Ручной
                'before_update',// Перед обновлением
                'emergency',    // Аварийный
            ])->default('manual');

            // Файлы
            $table->string('filename'); // Имя файла
            $table->string('path');     // Путь к файлу
            $table->string('storage_disk')->default('local'); // Диск хранилища
            $table->bigInteger('size')->nullable(); // Размер в байтах

            // База данных
            $table->string('database_name')->nullable();
            $table->integer('tables_count')->nullable();

            // Файлы системы
            $table->integer('files_count')->nullable();
            $table->json('included_directories')->nullable(); // Включенные директории
            $table->json('excluded_directories')->nullable(); // Исключенные директории

            // Статус
            $table->enum('status', [
                'pending',      // Ожидает
                'processing',   // В процессе
                'completed',    // Завершен
                'failed',       // Ошибка
                'verified',     // Проверен
                'corrupted',    // Поврежден
            ])->default('pending');

            // Даты
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration')->nullable(); // Длительность в секундах

            // Создатель
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            // Проверка
            $table->string('checksum')->nullable(); // Контрольная сумма
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');

            // Расписание
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_cron')->nullable();

            // Хранилище
            $table->integer('retention_days')->default(30); // Дней хранения
            $table->timestamp('expires_at')->nullable(); // Дата истечения

            // Результаты
            $table->json('logs')->nullable(); // Логи выполнения
            $table->text('error_message')->nullable(); // Сообщение об ошибке
            $table->json('statistics')->nullable(); // Статистика

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы
            $table->index(['type', 'status']);
            $table->index(['purpose', 'created_at']);
            $table->index('expires_at');
            $table->index('is_scheduled');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
