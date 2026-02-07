<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Например: "Люкс с видом на море"
            $table->string('type'); // Например: "standard", "deluxe", "suite"
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('capacity'); // Максимальное количество гостей
            $table->decimal('price_per_night', 10, 2);
            $table->integer('total_rooms')->default(1); // Количество таких номеров в отеле
            $table->integer('available_rooms')->default(1); // Доступно для бронирования
            $table->json('amenities')->nullable(); // Удобства в номере (JSON)
            $table->json('photos')->nullable(); // Массив фото номера
            $table->string('size')->nullable(); // Размер номера (кв.м)
            $table->json('bed_types')->nullable(); // Типы кроватей (JSON)
            $table->json('view')->nullable(); // Вид из окна (JSON)
            $table->json('extra_services')->nullable(); // Доп. услуги (JSON)
            $table->enum('status', ['available', 'unavailable', 'maintenance'])->default('available');
            $table->integer('order')->default(0); // Для сортировки
            $table->json('settings')->nullable(); // Настройки номера (JSON)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
