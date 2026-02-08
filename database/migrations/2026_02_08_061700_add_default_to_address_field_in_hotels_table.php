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
        // Для MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hotels MODIFY address VARCHAR(255) DEFAULT 'Не указан' NOT NULL");
        }

        // Для SQLite или других СУБД
        Schema::table('hotels', function (Blueprint $table) {
            // Для других СУБД, кроме MySQL
            if (DB::getDriverName() !== 'mysql') {
                $table->string('address')->default('Не указан')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hotels MODIFY address VARCHAR(255) NOT NULL");
        }

        Schema::table('hotels', function (Blueprint $table) {
            if (DB::getDriverName() !== 'mysql') {
                $table->string('address')->default(null)->change();
            }
        });
    }
};
