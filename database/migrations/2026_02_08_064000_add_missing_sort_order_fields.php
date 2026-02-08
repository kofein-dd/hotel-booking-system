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
        // Таблица hotels
        if (!Schema::hasColumn('hotels', 'sort_order')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_featured');
            });
        }

        // Таблица rooms
        if (!Schema::hasColumn('rooms', 'sort_order')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_featured');
            });
        }

        // Таблица facilities
        if (!Schema::hasColumn('facilities', 'sort_order')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_active');
            });
        }

        // Таблица pages (если есть)
        if (Schema::hasTable('pages') && !Schema::hasColumn('pages', 'sort_order')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('status');
            });
        }

        // Таблица faqs (если есть)
        if (Schema::hasTable('faqs') && !Schema::hasColumn('faqs', 'sort_order')) {
            Schema::table('faqs', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_active');
            });
        }

        // Обновляем существующие записи
        $tables = ['hotels', 'rooms', 'facilities', 'pages', 'faqs'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'sort_order')) {
                \DB::table($table)->whereNull('sort_order')->update(['sort_order' => 0]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Не удаляем поля для безопасности данных
        // Если нужно откатить, можно раскомментировать:
        /*
        Schema::table('hotels', function (Blueprint $table) {
            if (Schema::hasColumn('hotels', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::table('facilities', function (Blueprint $table) {
            if (Schema::hasColumn('facilities', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
        */
    }
};
