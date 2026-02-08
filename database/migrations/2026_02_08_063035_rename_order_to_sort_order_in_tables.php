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
        // Проверяем и переименовываем поле в таблице rooms
        if (Schema::hasColumn('rooms', 'order') && !Schema::hasColumn('rooms', 'sort_order')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('order', 'sort_order');
            });
        }

        // Проверяем и переименовываем поле в таблице facilities
        if (Schema::hasColumn('facilities', 'order') && !Schema::hasColumn('facilities', 'sort_order')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->renameColumn('order', 'sort_order');
            });
        }

        // Проверяем другие таблицы...
        $tablesWithOrder = ['hotels', 'pages', 'faqs']; // Добавьте другие таблицы при необходимости

        foreach ($tablesWithOrder as $tableName) {
            if (Schema::hasTable($tableName) &&
                Schema::hasColumn($tableName, 'order') &&
                !Schema::hasColumn($tableName, 'sort_order')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->renameColumn('order', 'sort_order');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем обратно
        if (Schema::hasColumn('rooms', 'sort_order') && !Schema::hasColumn('rooms', 'order')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('sort_order', 'order');
            });
        }

        if (Schema::hasColumn('facilities', 'sort_order') && !Schema::hasColumn('facilities', 'order')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->renameColumn('sort_order', 'order');
            });
        }

        $tablesWithSortOrder = ['hotels', 'pages', 'faqs'];

        foreach ($tablesWithSortOrder as $tableName) {
            if (Schema::hasTable($tableName) &&
                Schema::hasColumn($tableName, 'sort_order') &&
                !Schema::hasColumn($tableName, 'order')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->renameColumn('sort_order', 'order');
                });
            }
        }
    }
};
