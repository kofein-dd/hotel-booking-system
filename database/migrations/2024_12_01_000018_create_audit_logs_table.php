<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_number')->unique(); // Уникальный номер лога

            // Пользователь и действие
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_ip')->nullable();
            $table->string('user_agent')->nullable();

            // Действие
            $table->string('action'); // create, update, delete, login, etc.
            $table->string('model_type')->nullable(); // Модель (например: User, Booking)
            $table->unsignedBigInteger('model_id')->nullable(); // ID модели

            // Данные
            $table->json('old_data')->nullable(); // Старые данные
            $table->json('new_data')->nullable(); // Новые данные
            $table->json('changed_fields')->nullable(); // Измененные поля
            $table->text('description')->nullable(); // Описание действия

            // URL и метод
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->json('request_data')->nullable(); // Данные запроса

            // Контекст
            $table->string('context')->nullable(); // Контекст действия
            $table->string('level')->default('info'); // info, warning, error, critical
            $table->json('tags')->nullable(); // Теги для фильтрации

            // Связи
            $table->foreignId('related_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');

            // Статус
            $table->boolean('is_system')->default(false); // Системное действие
            $table->boolean('is_api')->default(false); // API запрос
            $table->boolean('is_background')->default(false); // Фоновое действие

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['action', 'created_at']);
            $table->index('level');
            $table->index('created_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
