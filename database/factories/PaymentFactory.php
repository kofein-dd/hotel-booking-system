<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $booking = \App\Models\Booking::factory()->create();

        return [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => $booking->total_price,
            'method' => fake()->randomElement(['credit_card', 'debit_card', 'bank_transfer', 'online']),
            'status' => 'completed',
            'transaction_id' => 'TRX' . fake()->unique()->numberBetween(100000, 999999),
            'payment_date' => now(),
            'payment_details' => [
                'card_last_four' => fake()->numerify('####'),
                'payment_gateway' => 'stripe',
            ],
            'currency' => 'RUB',
            'refund_amount' => null,
            'refund_date' => null,
            'refund_reason' => null,
        ];
    }

    /**
     * Pending payment
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_date' => null,
            'transaction_id' => null,
        ]);
    }

    /**
     * Failed payment
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'payment_date' => now(),
        ]);
    }

    /**
     * Refunded payment
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refund_amount' => $this->faker->randomFloat(2, 100, 1000),
            'refund_date' => now(),
            'refund_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Partial refund
     */
    public function partialRefund(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partial_refund',
            'refund_amount' => $this->faker->randomFloat(2, 100, $attributes['amount'] / 2),
            'refund_date' => now(),
            'refund_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Specific payment method
     */
    public function method(string $method): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => $method,
        ]);
    }
}
