<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique(); // Уникальный номер отчета
            $table->string('name'); // Название отчета
            $table->string('slug')->unique(); // Уникальный идентификатор

            // Тип и категория отчета
            $table->enum('type', [
                'financial',    // Финансовый
                'booking',      // Бронирования
                'user',         // Пользователи
                'room',         // Номера
                'revenue',      // Выручка
                'occupancy',    // Заполняемость
                'custom',       // Пользовательский
            ]);

            $table->enum('category', [
                'daily',        // Ежедневный
                'weekly',       // Еженедельный
                'monthly',      // Ежемесячный
                'quarterly',    // Квартальный
                'yearly',       // Годовой
                'adhoc',        // По требованию
            ])->default('adhoc');

            // Параметры отчета
            $table->json('parameters')->nullable(); // Параметры отчета (JSON)
            $table->json('filters')->nullable(); // Фильтры (JSON)
            $table->date('date_from')->nullable(); // Дата начала периода
            $table->date('date_to')->nullable();   // Дата окончания периода

            // Статус отчета
            $table->enum('status', [
                'pending',      // Ожидает генерации
                'processing',   // В обработке
                'completed',    // Завершен
                'failed',       // Ошибка
                'archived',     // В архиве
            ])->default('pending');

            // Результаты отчета
            $table->json('data')->nullable(); // Данные отчета (JSON)
            $table->json('summary')->nullable(); // Сводка (JSON)
            $table->json('charts')->nullable(); // Данные для графиков (JSON)

            // Файлы
            $table->string('file_path')->nullable(); // Путь к файлу (PDF, Excel)
            $table->string('file_format')->nullable(); // Формат файла: pdf, excel, csv
            $table->integer('file_size')->nullable(); // Размер файла в байтах

            // Время выполнения
            $table->timestamp('generated_at')->nullable(); // Когда сгенерирован
            $table->integer('generation_time')->nullable(); // Время генерации в секундах

            // Создатель
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');

            // Расписание
            $table->boolean('is_scheduled')->default(false); // Запланированный отчет
            $table->string('schedule_cron')->nullable(); // Cron выражение для расписания
            $table->timestamp('last_scheduled_run')->nullable(); // Последний запуск по расписанию
            $table->timestamp('next_scheduled_run')->nullable(); // Следующий запуск по расписанию

            // Настройки
            $table->boolean('is_auto_generate')->default(false); // Автоматическая генерация
            $table->boolean('send_email')->default(false); // Отправлять на email
            $table->json('email_recipients')->nullable(); // Получатели email (JSON)
            $table->json('notification_settings')->nullable(); // Настройки уведомлений

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы
            $table->index(['type', 'status']);
            $table->index(['category', 'generated_at']);
            $table->index('created_by');
            $table->index('is_scheduled');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
