<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TableSession>
 */
class TableSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $opened = $this->faker->dateTimeBetween('-3 months', 'now');
        $closed = (clone $opened)->modify('+'.rand(30, 180).' minutes');
        return [
            'table_id' => null, // Set in seeder
            'editor_id' => 1, // Always set in seeder for correct user
            'session_number' => $this->faker->unique()->numberBetween(1, 10000),
            'date' => $opened->format('Y-m-d'),
            'unique_token' => $this->faker->unique()->uuid,
            'opened_at' => $opened,
            'closed_at' => $closed,
            'opened_by' => 1,
            'closed_by' => 1,
            'status' => $this->faker->randomElement(['open', 'closed', 'reopened']),
        ];
    }
}
