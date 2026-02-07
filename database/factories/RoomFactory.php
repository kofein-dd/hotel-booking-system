<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['standard', 'superior', 'deluxe', 'suite', 'presidential'];
        $type = fake()->randomElement($types);

        $prices = [
            'standard' => 3000,
            'superior' => 4500,
            'deluxe' => 6000,
            'suite' => 10000,
            'presidential' => 20000,
        ];

        $capacities = [
            'standard' => 2,
            'superior' => 2,
            'deluxe' => 3,
            'suite' => 4,
            'presidential' => 6,
        ];

        return [
            'hotel_id' => \App\Models\Hotel::factory(),
            'room_number' => fake()->unique()->numberBetween(100, 500),
            'type' => $type,
            'name' => ucfirst($type) . ' Room',
            'description' => fake()->paragraph(),
            'capacity' => $capacities[$type],
            'price_per_night' => $prices[$type],
            'photos' => [
                fake()->imageUrl(800, 600, 'bedroom'),
                fake()->imageUrl(800, 600, 'bathroom'),
                fake()->imageUrl(800, 600, 'view'),
            ],
            'videos' => null,
            'amenities' => $this->getAmenitiesByType($type),
            'status' => 'available',
            'size' => fake()->numberBetween(20, 80),
            'bed_type' => fake()->randomElement(['single', 'double', 'twin', 'queen', 'king']),
            'view' => fake()->randomElement(['sea', 'garden', 'pool', 'city']),
            'floor' => fake()->numberBetween(1, 10),
            'extra_beds' => fake()->numberBetween(0, 2),
            'extra_bed_price' => 1000,
            'max_occupancy' => $capacities[$type] + 2,
        ];
    }

    /**
     * Get amenities by room type
     */
    private function getAmenitiesByType(string $type): array
    {
        $baseAmenities = ['wifi', 'tv', 'air_conditioning', 'minibar', 'safe'];

        $additionalAmenities = [
            'standard' => ['hairdryer'],
            'superior' => ['hairdryer', 'bathrobe', 'slippers'],
            'deluxe' => ['hairdryer', 'bathrobe', 'slippers', 'jacuzzi', 'balcony'],
            'suite' => ['hairdryer', 'bathrobe', 'slippers', 'jacuzzi', 'balcony', 'living_room', 'kitchenette'],
            'presidential' => ['hairdryer', 'bathrobe', 'slippers', 'jacuzzi', 'balcony', 'living_room',
                'kitchen', 'dining_area', 'office', 'private_pool'],
        ];

        return array_merge($baseAmenities, $additionalAmenities[$type] ?? []);
    }

    /**
     * Occupied room
     */
    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'occupied',
        ]);
    }

    /**
     * Maintenance room
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
        ]);
    }

    /**
     * Reserved room
     */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reserved',
        ]);
    }

    /**
     * Specific room type
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'name' => ucfirst($type) . ' Room',
        ]);
    }
}
