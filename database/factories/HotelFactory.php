<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Hotel',
            'description' => fake()->paragraphs(3, true),
            'photos' => [
                fake()->imageUrl(800, 600, 'hotel'),
                fake()->imageUrl(800, 600, 'room'),
                fake()->imageUrl(800, 600, 'pool'),
            ],
            'videos' => null,
            'coordinates' => [
                'lat' => fake()->latitude(44, 46),
                'lng' => fake()->longitude(33, 35),
            ],
            'contact_info' => [
                'phone' => fake()->phoneNumber(),
                'email' => fake()->companyEmail(),
                'address' => fake()->address(),
            ],
            'status' => 'active',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'rules' => [
                'check_in' => 'После 14:00',
                'check_out' => 'До 12:00',
                'pets' => 'Не разрешены',
                'smoking' => 'Только в специальных местах',
            ],
            'amenities' => ['wifi', 'pool', 'spa', 'restaurant', 'parking', 'gym'],
            'social_links' => [
                'facebook' => fake()->url(),
                'instagram' => fake()->url(),
                'twitter' => fake()->url(),
            ],
        ];
    }

    /**
     * Inactive hotel
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Under maintenance
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
        ]);
    }
}
