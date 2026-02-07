<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Администратор',
            'email' => 'admin@hotel.com',
            'password' => Hash::make('password'),
            'phone' => '+7 (999) 123-45-67',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Модератор',
            'email' => 'moderator@hotel.com',
            'password' => Hash::make('password'),
            'phone' => '+7 (999) 123-45-68',
            'role' => 'moderator',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        echo "Администратор создан:\n";
        echo "Email: admin@hotel.com\n";
        echo "Пароль: password\n\n";

        echo "Модератор создан:\n";
        echo "Email: moderator@hotel.com\n";
        echo "Пароль: password\n";
    }
}
