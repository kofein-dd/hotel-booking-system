<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'free_night'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('per_user_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('room_types')->nullable();
            $table->json('booking_days')->nullable();
            $table->json('excluded_dates')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Добавляем поле discount_code в bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('discount_code')->nullable()->after('total_price');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['discount_code', 'discount_amount']);
        });

        Schema::dropIfExists('discounts');
    }
};
