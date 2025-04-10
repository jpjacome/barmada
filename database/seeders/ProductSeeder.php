<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the products
        $products = [
            [
                'name' => 'Vino Hervido',
                'price' => 4,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup-hot',
            ],
            [
                'name' => 'Canelazo',
                'price' => 4,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup-hot',
            ],
            [
                'name' => 'Cerveza',
                'price' => 4,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup',
            ],
            [
                'name' => 'Vino',
                'price' => 5,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup',
            ],
            [
                'name' => 'Ron',
                'price' => 7,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup',
            ],
            [
                'name' => 'Coca Cola',
                'price' => 3,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup-straw',
            ],
            [
                'name' => 'Agua',
                'price' => 1,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-cup-straw',
            ],
            [
                'name' => 'Nachos',
                'price' => 5,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-pie-chart',
            ],
            [
                'name' => 'Tablita',
                'price' => 5,
                'icon_type' => 'bootstrap',
                'icon_value' => 'bi-egg-fried',
            ],
        ];

        // Insert the products
        foreach ($products as $product) {
            Product::create($product);
        }
    }
} 