<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Сначала добавим недостающие поля, если их нет
        Schema::table('hotels', function (Blueprint $table) {
            // Проверяем существование полей перед добавлением

            // Поле stars
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

            // Поле is_featured
            if (!Schema::hasColumn('hotels', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('status');
            }

            // Поле phone (если нет)
            if (!Schema::hasColumn('hotels', 'phone')) {
                $table->string('phone')->nullable()->after('country');
            }

            // Поле email (если нет)
            if (!Schema::hasColumn('hotels', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            // Поле website (если нет)
            if (!Schema::hasColumn('hotels', 'website')) {
                $table->string('website')->nullable()->after('email');
            }

            // Поле description (если нет)
            if (!Schema::hasColumn('hotels', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
        });

        // Теперь установим значения по умолчанию для существующих полей
        // Для MySQL
        if (DB::getDriverName() === 'mysql') {
            // Сначала обновим существующие NULL значения
            DB::table('hotels')->whereNull('address')->update(['address' => 'Не указан']);
            DB::table('hotels')->whereNull('city')->update(['city' => 'Не указан']);
            DB::table('hotels')->whereNull('country')->update(['country' => 'Россия']);

            // Затем изменим структуру полей
            DB::statement("ALTER TABLE hotels
                MODIFY address VARCHAR(255) DEFAULT 'Не указан' NOT NULL,
                MODIFY city VARCHAR(100) DEFAULT 'Не указан' NOT NULL,
                MODIFY country VARCHAR(100) DEFAULT 'Россия' NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ВНИМАНИЕ: Этот down метод не удалит добавленные поля,
        // чтобы избежать потери данных. Если нужно удалить поля,
        // сделайте это вручную.

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hotels
                MODIFY address VARCHAR(255) NOT NULL,
                MODIFY city VARCHAR(100) NOT NULL,
                MODIFY country VARCHAR(100) NOT NULL
            ");
        }
    }
};
