<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 days', '+30 days');
        $checkOut = (clone $checkIn)->modify('+' . fake()->numberBetween(1, 14) . ' days');

        $room = \App\Models\Room::factory()->create();
        $nights = $checkIn->diff($checkOut)->days;
        $totalPrice = $room->price_per_night * $nights;

        return [
            'user_id' => \App\Models\User::factory(),
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests_count' => fake()->numberBetween(1, $room->capacity),
            'total_price' => $totalPrice,
            'status' => 'confirmed',
            'cancellation_date' => null,
            'cancellation_reason' => null,
            'special_requests' => fake()->optional()->sentence(),
            'booking_source' => 'website',
            'payment_status' => 'paid',
            'confirmation_number' => 'BK' . fake()->unique()->numberBetween(100000, 999999),
        ];
    }

    /**
     * Pending booking
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    /**
     * Cancelled booking
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancellation_date' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Completed booking
     */
    public function completed(): static
    {
        $checkIn = fake()->dateTimeBetween('-30 days', '-1 days');
        $checkOut = (clone $checkIn)->modify('+' . fake()->numberBetween(1, 14) . ' days');

        return $this->state(fn (array $attributes) => [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => 'completed',
        ]);
    }

    /**
     * Booking with specific dates
     */
    public function dates($checkIn, $checkOut): static
    {
        $room = \App\Models\Room::factory()->create();
        $nights = $checkIn->diff($checkOut)->days;
        $totalPrice = $room->price_per_night * $nights;

        return $this->state(fn (array $attributes) => [
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_price' => $totalPrice,
        ]);
    }
}
