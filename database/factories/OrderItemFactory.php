<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_uid' => fake()->unique()->bothify('P-######'),
            'product_name' => fake()->words(3, true),
            'category_uid' => fake()->bothify('C-######'),
            'category_name' => fake()->word(),
            'seller_uid' => fake()->bothify('S-######'),
            'seller_name' => fake()->company(),
            'warehouse_uid' => fake()->bothify('WH-######'),
            'warehouse_name' => fake()->city(),
            'color_family' => fake()->safeColorName(),
            'size' => 'default',
            'quantity' => fake()->numberBetween(1, 3),
            'mrp_price' => fake()->randomFloat(2, 50, 2000),
            'discount_amount' => 0,
            'commission_amount' => 0,
        ];
    }
}
