<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique(); // Уникальный номер брони
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');

            // Даты бронирования
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('nights'); // Количество ночей
            $table->integer('guests_count');

            // Цены и оплата
            $table->decimal('room_price_per_night', 10, 2);
            $table->decimal('subtotal', 10, 2); // Стоимость до скидок
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('extra_charges', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2);
            $table->string('currency')->default('USD');

            // Статус
            $table->enum('status', [
                'pending',      // Ожидает подтверждения
                'confirmed',    // Подтверждено
                'cancelled',    // Отменено
                'completed',    // Завершено (гость выехал)
                'no_show',      // Неявка
            ])->default('pending');

            // Информация о гостях
            $table->json('guest_info')->nullable(); // Основной гость
            $table->json('additional_guests')->nullable(); // Доп. гости

            // Отмена
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();

            // Дополнительно
            $table->text('special_requests')->nullable();
            $table->json('metadata')->nullable(); // Любые доп. данные (JSON)
            $table->timestamps();
            $table->softDeletes();

            // Индексы для поиска
            $table->index(['check_in', 'check_out']);
            $table->index(['status', 'check_in']);
            $table->index('booking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
