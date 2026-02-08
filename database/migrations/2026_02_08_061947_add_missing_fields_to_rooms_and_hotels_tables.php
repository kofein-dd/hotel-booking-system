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
        // Добавляем поля в таблицу rooms
        Schema::table('rooms', function (Blueprint $table) {
            // Поле is_featured для выделенных номеров
            if (!Schema::hasColumn('rooms', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('status');
            }

            // Поле order для сортировки
            if (!Schema::hasColumn('rooms', 'order')) {
                $table->integer('order')->default(0)->after('is_featured');
            }

            // Поле max_occupancy (максимальная вместимость)
            if (!Schema::hasColumn('rooms', 'max_occupancy')) {
                $table->integer('max_occupancy')->default(2)->after('capacity');
            }

            // Поле amenities (удобства)
            if (!Schema::hasColumn('rooms', 'amenities')) {
                $table->json('amenities')->nullable()->after('max_occupancy');
            }

            // Поле size (размер номера в м²)
            if (!Schema::hasColumn('rooms', 'size')) {
                $table->decimal('size', 8, 2)->nullable()->after('amenities');
            }

            // Поле view (вид из окна)
            if (!Schema::hasColumn('rooms', 'view')) {
                $table->string('view')->nullable()->after('size');
            }
        });

        // Добавляем недостающие поля в таблицу hotels
        Schema::table('hotels', function (Blueprint $table) {
            // Поле is_featured для выделенных отелей
            if (!Schema::hasColumn('hotels', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('status');
            }

            // Поле stars (количество звезд)
            if (!Schema::hasColumn('hotels', 'stars')) {
                $table->integer('stars')->default(3)->after('website');
            }

            // Поле check_in_time
            if (!Schema::hasColumn('hotels', 'check_in_time')) {
                $table->time('check_in_time')->default('14:00:00')->after('stars');
            }

            // Поле check_out_time
            if (!Schema::hasColumn('hotels', 'check_out_time')) {
                $table->time('check_out_time')->default('12:00:00')->after('check_in_time');
            }

            // Поле amenities (удобства отеля)
            if (!Schema::hasColumn('hotels', 'amenities')) {
                $table->json('amenities')->nullable()->after('check_out_time');
            }

            // Поле description
            if (!Schema::hasColumn('hotels', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }

            // Поле policies (правила отеля)
            if (!Schema::hasColumn('hotels', 'policies')) {
                $table->text('policies')->nullable()->after('amenities');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // При откате удаляем добавленные поля
        Schema::table('rooms', function (Blueprint $table) {
            $columnsToDrop = ['is_featured', 'order', 'max_occupancy', 'amenities', 'size', 'view'];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('rooms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('hotels', function (Blueprint $table) {
            $columnsToDrop = ['is_featured', 'stars', 'check_in_time', 'check_out_time', 'amenities', 'description', 'policies'];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('hotels', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
