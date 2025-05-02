<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user if it doesn't exist
        if (!User::where('email', 'admin@golems.bar')->exists()) {
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@golems.bar',
            ]);
        }

        // Create a default editor user if it doesn't exist
        $editor = User::firstOrCreate(
            ['email' => 'editor@golems.bar'],
            [
                'name' => 'Default Editor',
                'password' => bcrypt('password'),
                'is_editor' => true,
            ]
        );

        // Store the editor ID in config for use in ProductSeeder
        config(['barmada.default_editor_id' => $editor->id]);
        $this->call(ProductSeeder::class);
    }
}
