<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');

            $table->timestamp('used_at')->useCurrent();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->string('booking_number')->nullable();
            $table->json('usage_data')->nullable(); // Данные об использовании (JSON)

            // Индексы
            $table->index(['user_id', 'discount_id']);
            $table->index(['discount_id', 'used_at']);
            $table->index('booking_id');

            $table->timestamps();

            // Уникальность: пользователь не может использовать один промокод несколько раз
            // (если не указано иное в настройках промокода)
            $table->unique(['discount_id', 'user_id', 'booking_id'], 'discount_user_booking_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_user');
    }
};
