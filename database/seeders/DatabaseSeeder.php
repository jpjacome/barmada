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

        // Call the product seeder
        $this->call([
            ProductSeeder::class,
        ]);
    }
}
