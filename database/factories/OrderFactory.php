<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => fake()->unique()->bothify('O-######'),
            'environment' => Order::ENVIRONMENT_MONGO,
            'status' => fake()->randomElement(['PROCESSING', 'DELIVERED', 'CANCELLED']),
            'payment_method' => fake()->randomElement(['COD', 'CARD', 'MOBILE_BANKING']),
            'payment_status' => fake()->randomElement(['INITIAL', 'COMPLETED']),
            'created_at_external' => fake()->dateTimeBetween('-1 year'),
            'updated_at_external' => fake()->dateTimeBetween('-1 year'),
            'currency' => 'BDT',
            'total_amount' => fake()->randomFloat(2, 100, 5000),
            'customer_email' => fake()->safeEmail(),
        ];
    }
}
