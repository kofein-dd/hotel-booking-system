<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('banned_until');
            $table->boolean('email_notifications')->default(true)->after('notification_preferences');
            $table->boolean('push_notifications')->default(true)->after('email_notifications');
            $table->boolean('sms_notifications')->default(false)->after('push_notifications');
            $table->timestamp('email_verified_at')->nullable()->after('sms_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_preferences',
                'email_notifications',
                'push_notifications',
                'sms_notifications',
                'email_verified_at'
            ]);
        });
    }
};
