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
            $table->string('room_number')->unique();
            $table->enum('type', ['standard', 'superior', 'deluxe', 'suite', 'presidential'])->default('standard');
            $table->string('name');
            $table->text('description');
            $table->integer('capacity');
            $table->decimal('price_per_night', 10, 2);
            $table->json('photos')->nullable();
            $table->json('videos')->nullable();
            $table->json('amenities')->nullable();
            $table->enum('status', ['available', 'occupied', 'maintenance', 'reserved'])->default('available');
            $table->decimal('size', 8, 2)->nullable();
            $table->enum('bed_type', ['single', 'double', 'twin', 'queen', 'king'])->nullable();
            $table->string('view')->nullable();
            $table->integer('floor')->nullable();
            $table->integer('extra_beds')->default(0);
            $table->decimal('extra_bed_price', 10, 2)->default(0);
            $table->integer('max_occupancy')->default(2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('room_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->boolean('is_available')->default(true);
            $table->decimal('price_override', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_availability');
        Schema::dropIfExists('rooms');
    }
};
