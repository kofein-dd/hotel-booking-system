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
        // Добавляем поле sort_order если его нет
        if (!Schema::hasColumn('hotels', 'sort_order')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_featured');
            });
        }

        // Обновляем существующие записи
        \DB::table('hotels')->whereNull('sort_order')->update(['sort_order' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем поле sort_order если нужно
        if (Schema::hasColumn('hotels', 'sort_order')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
