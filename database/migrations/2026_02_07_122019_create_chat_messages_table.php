<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('message');
            $table->json('attachment')->nullable();
            $table->boolean('is_admin_message')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->enum('message_type', ['text', 'image', 'file', 'system'])->default('text');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['is_admin_message', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
