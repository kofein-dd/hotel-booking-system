<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем администратора
        User::create([
            'name' => 'Администратор',
            'email' => 'admin@hotel.com',
            'password' => Hash::make('password'),
            'phone' => '+7 (999) 123-45-67',
            'role' => 'admin',
            'status' => 'active',
        ])->assignRole('admin');

        // Создаем модератора
        User::create([
            'name' => 'Модератор',
            'email' => 'moderator@hotel.com',
            'password' => Hash::make('password'),
            'phone' => '+7 (999) 123-45-68',
            'role' => 'moderator',
            'status' => 'active',
        ])->assignRole('moderator');

        // Создаем обычных пользователей
        User::factory()->count(10)->create()->each(function ($user) {
            $user->assignRole('user');
        });

        // Создаем забаненного пользователя
        User::factory()->banned()->create([
            'name' => 'Забаненный Пользователь',
            'email' => 'banned@example.com',
            'password' => Hash::make('password'),
        ])->assignRole('user');
    }
}
