<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general')->index(); // Группа настроек
            $table->string('key')->unique(); // Ключ настройки
            $table->text('value')->nullable(); // Значение
            $table->string('type')->default('string'); // Тип: string, integer, boolean, json, array
            $table->text('description')->nullable(); // Описание
            $table->json('options')->nullable(); // Опции для select и т.д.
            $table->integer('order')->default(0); // Порядок отображения
            $table->boolean('is_public')->default(false); // Публичная настройка
            $table->boolean('is_required')->default(false); // Обязательная настройка
            $table->json('validation_rules')->nullable(); // Правила валидации
            $table->json('metadata')->nullable(); // Метаданные

            $table->timestamps();

            // Индексы
            $table->index(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
