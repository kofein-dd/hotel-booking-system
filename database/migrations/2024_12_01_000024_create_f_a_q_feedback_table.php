<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('f_a_q_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('session_id')->nullable();
            $table->enum('feedback', ['helpful', 'not_helpful'])->nullable();
            $table->text('comment')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('faq_id');
            $table->index('user_id');
            $table->unique(['faq_id', 'user_id', 'session_id'], 'unique_feedback');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f_a_q_feedback');
    }
};
