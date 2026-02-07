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
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['credit_card', 'debit_card', 'bank_transfer', 'cash', 'online'])->default('online');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'partial_refund'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->json('payment_details')->nullable();
            $table->enum('currency', ['RUB', 'USD', 'EUR'])->default('RUB');
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'payment_date']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
