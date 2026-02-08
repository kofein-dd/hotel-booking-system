<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // Иконка FontAwesome или SVG
            $table->text('description')->nullable();
            $table->string('type')->default('general'); // general, room, hotel, bathroom, kitchen
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Промежуточная таблица для связи отелей и удобств
        Schema::create('facility_hotel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable(); // Описание конкретного удобства для этого отеля
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['facility_id', 'hotel_id']);
        });

        // Промежуточная таблица для связи номеров и удобств
        Schema::create('facility_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable(); // Описание конкретного удобства для этого номера
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['facility_id', 'room_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_room');
        Schema::dropIfExists('facility_hotel');
        Schema::dropIfExists('facilities');
    }
};
