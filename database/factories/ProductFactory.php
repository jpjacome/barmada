<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->optional()->sentence(),
            'price' => $this->faker->randomFloat(2, 2, 30),
            'category_id' => null, // Set in seeder for relational integrity
            'editor_id' => 1, // Always set in seeder for correct user
        ];
    }
}
