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
            $table->text('description');
            $table->json('photos')->nullable();
            $table->json('videos')->nullable();
            $table->json('coordinates')->nullable();
            $table->json('contact_info')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->json('working_days')->nullable();
            $table->time('check_in_time')->default('14:00:00');
            $table->time('check_out_time')->default('12:00:00');
            $table->json('rules')->nullable();
            $table->json('amenities')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
