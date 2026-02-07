<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->json('photos')->nullable(); // Массив путей к фото
            $table->json('videos')->nullable(); // Массив ссылок на видео (YouTube/Vimeo)
            $table->string('address');
            $table->string('city');
            $table->string('country');
            $table->decimal('latitude', 10, 7)->nullable(); // Координаты для карты
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->json('contact_info')->nullable(); // Доп. контакты (JSON)
            $table->json('amenities')->nullable(); // Удобства отеля (бассейн, Wi-Fi и т.д.)
            $table->json('social_links')->nullable(); // Ссылки на соцсети
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->json('non_working_days')->nullable(); // Массив дат, когда отель не работает
            $table->json('settings')->nullable(); // Настройки отеля (JSON)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
