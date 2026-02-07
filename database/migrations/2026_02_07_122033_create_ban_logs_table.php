<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ban_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->text('reason');
            $table->timestamp('banned_until')->nullable();
            $table->enum('type', ['full', 'booking', 'chat', 'comments'])->default('full');
            $table->boolean('is_permanent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('unbanned_at')->nullable();
            $table->text('unban_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'unbanned_at']);
            $table->index(['banned_until', 'is_permanent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ban_logs');
    }
};
