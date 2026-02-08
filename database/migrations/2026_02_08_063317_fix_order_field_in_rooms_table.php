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
        // Проверяем текущую структуру
        if (!Schema::hasColumn('rooms', 'sort_order') && !Schema::hasColumn('rooms', 'order')) {
            // Если нет ни одного поля, создаем sort_order
            Schema::table('rooms', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_featured');
            });
        } elseif (Schema::hasColumn('rooms', 'order') && !Schema::hasColumn('rooms', 'sort_order')) {
            // Если есть только order, переименовываем в sort_order
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('order', 'sort_order');
            });
        } elseif (Schema::hasColumn('rooms', 'sort_order') && Schema::hasColumn('rooms', 'order')) {
            // Если есть оба поля, удаляем order и копируем данные
            Schema::table('rooms', function (Blueprint $table) {
                // Копируем данные из order в sort_order для существующих записей
                \DB::statement('UPDATE rooms SET sort_order = `order` WHERE `order` IS NOT NULL');
                // Удаляем поле order
                $table->dropColumn('order');
            });
        }

        // Убедимся, что поле sort_order существует
        if (!Schema::hasColumn('rooms', 'sort_order')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_featured');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем поле order если нужно
        if (!Schema::hasColumn('rooms', 'order') && Schema::hasColumn('rooms', 'sort_order')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('sort_order', 'order');
            });
        }
    }
};
