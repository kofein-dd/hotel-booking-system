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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('guests_count');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed', 'no_show', 'refunded'])->default('pending');
            $table->timestamp('cancellation_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('special_requests')->nullable();
            $table->string('booking_source')->default('website');
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'refunded', 'failed'])->default('pending');
            $table->string('confirmation_number')->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['check_in', 'check_out']);
            $table->index(['status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
