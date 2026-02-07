<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggested_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('question');
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'added_to_faq'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->onDelete('set null');
            $table->integer('votes')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('faq_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggested_questions');
    }
};
