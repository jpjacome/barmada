<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;

class AnalyticsTestSeeder extends Seeder
{
    public function run(): void
    {
        // Create a test editor user
        $editor = User::factory()->create([
            'is_editor' => true,
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create categories, products, tables
        $categories = Category::factory(10)->create(['editor_id' => $editor->id]);
        $products = Product::factory(50)->make()->each(function ($product) use ($categories, $editor) {
            $product->category_id = $categories->random()->id;
            $product->editor_id = $editor->id;
            $product->save();
        });
        $tables = Table::factory(15)->create(['editor_id' => $editor->id]);

        // For each day in the last 90 days, create random orders and sessions
        foreach (range(0, 89) as $daysAgo) {
            $date = now()->subDays($daysAgo);
            // Create table sessions
            TableSession::factory(rand(2, 6))->make()->each(function ($session) use ($tables, $editor, $date) {
                $session->table_id = $tables->random()->id;
                $session->editor_id = $editor->id;
                $session->opened_at = $date->copy()->setTime(rand(12, 22), rand(0, 59));
                $session->closed_at = (clone $session->opened_at)->addMinutes(rand(30, 180));
                $session->save();
            });
            // Create orders
            Order::factory(rand(5, 20))->make()->each(function ($order) use ($editor, $tables, $products, $date) {
                $order->editor_id = $editor->id;
                $order->table_id = $tables->random()->id;
                $order->created_at = $date->copy()->setTime(rand(12, 23), rand(0, 59));
                $order->updated_at = $order->created_at;
                $order->save();
                // Create order items
                OrderItem::factory(rand(1, 5))->make()->each(function ($item) use ($order, $products) {
                    $item->order_id = $order->id;
                    $item->product_id = $products->random()->id;
                    $item->price = $products->find($item->product_id)->price;
                    $item->save();
                });
            });
        }
    }
}
