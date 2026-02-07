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
            $table->uuid('conversation_id')->index(); // Идентификатор диалога

            // Участники чата
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');

            // Связь с бронированием (если чат связан с бронированием)
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');

            // Сообщение
            $table->text('message');
            $table->json('attachments')->nullable(); // Вложения (фото, документы)
            $table->enum('message_type', [
                'text',
                'image',
                'document',
                'system', // Системные сообщения
            ])->default('text');

            // Статус сообщения
            $table->boolean('is_admin_message')->default(false); // Сообщение от админа
            $table->timestamp('read_at')->nullable(); // Когда прочитано
            $table->timestamp('delivered_at')->nullable(); // Когда доставлено

            // Информация об удалении
            $table->timestamp('deleted_at')->nullable(); // Когда удалено (мягкое удаление)
            $table->foreignId('deleted_by')->nullable()->constrained('users')->onDelete('set null');

            // Метаданные
            $table->json('metadata')->nullable();

            // Индексы для поиска
            $table->index(['conversation_id', 'created_at']);
            $table->index(['user_id', 'is_admin_message']);
            $table->index(['booking_id', 'created_at']);
            $table->index('read_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
