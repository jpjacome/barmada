<?php

namespace Database\Factories;

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
            'editor_id' => 1, // Always set in seeder for correct user
            'table_id' => null, // Set in seeder
            'status' => 'pending',
            'total_amount' => $this->faker->randomFloat(2, 10, 200),
            'amount_paid' => 0,
            'amount_left' => 0,
            'is_grouped' => false,
            'created_at' => now(), // Overwritten in seeder for date spread
            'updated_at' => now(),
        ];
    }
}
