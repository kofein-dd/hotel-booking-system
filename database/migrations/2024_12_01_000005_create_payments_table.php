<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Информация о платеже
            $table->string('payment_number')->unique(); // Уникальный номер платежа
            $table->decimal('amount', 10, 2);
            $table->decimal('amount_received', 10, 2)->nullable(); // Фактически полученная сумма
            $table->string('currency')->default('USD');

            // Метод оплаты
            $table->enum('method', [
                'credit_card',
                'debit_card',
                'bank_transfer',
                'paypal',
                'stripe',
                'yookassa',
                'cash',
                'other'
            ]);

            // Статус платежа
            $table->enum('status', [
                'pending',      // Ожидает оплаты
                'processing',   // В обработке
                'completed',    // Успешно завершен
                'failed',       // Неудача
                'refunded',     // Возвращен
                'partially_refunded', // Частично возвращен
                'cancelled',    // Отменен
            ])->default('pending');

            // Внешние идентификаторы
            $table->string('transaction_id')->nullable()->unique(); // ID транзакции платежной системы
            $table->string('gateway_response_id')->nullable(); // Ответ платежного шлюза
            $table->json('gateway_response')->nullable(); // Полный ответ платежного шлюза (JSON)

            // Даты
            $table->timestamp('payment_date')->nullable(); // Дата фактической оплаты
            $table->timestamp('refund_date')->nullable(); // Дата возврата
            $table->date('due_date')->nullable(); // Дата, до которой нужно оплатить

            // Информация о возврате
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_transaction_id')->nullable();

            // Детали платежа
            $table->json('payment_details')->nullable(); // Детали (маска карты, email PayPal и т.д.)
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Дополнительные данные (JSON)

            // Индексы для поиска
            $table->index(['status', 'payment_date']);
            $table->index('transaction_id');
            $table->index('payment_number');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
