<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', [
                'booking_confirmation',
                'booking_cancellation',
                'payment_success',
                'payment_failed',
                'reminder',
                'announcement',
                'chat_message',
                'system'
            ]);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('channel', ['email', 'sms', 'push', 'telegram', 'in_app'])->default('in_app');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['scheduled_at', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
