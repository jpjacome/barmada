<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_number' => $this->faker->unique()->numberBetween(1, 200),
            'orders' => 0,
            'reference' => $this->faker->optional()->uuid,
            'editor_id' => 1, // Always set in seeder for correct user
        ];
    }
}
