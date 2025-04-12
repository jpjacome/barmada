<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class MigrateOrdersToItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:migrate-to-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing orders to use the new order items structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of orders to order items...');
        
        $orders = Order::all();
        $this->info("Found {$orders->count()} orders to migrate");
        
        foreach ($orders as $order) {
            $this->info("Processing order #{$order->id}");
            
            // Calculate total amount for the order
            $totalAmount = 0;
            
            // Process each product field
            for ($i = 1; $i <= 9; $i++) {
                $qtyField = "product{$i}_qty";
                $qty = $order->$qtyField ?? 0;
                
                if ($qty > 0) {
                    $product = Product::find($i);
                    if ($product) {
                        $price = $product->price;
                        $totalAmount += $qty * $price;
                        
                        // Create individual order items
                        for ($j = 0; $j < $qty; $j++) {
                            OrderItem::create([
                                'order_id' => $order->id,
                                'product_id' => $i,
                                'quantity' => 1,
                                'price' => $price,
                                'is_paid' => false,
                                'item_index' => $j
                            ]);
                        }
                    }
                }
            }
            
            // Update order total
            $order->total_amount = $totalAmount;
            $order->save();
        }
        
        $this->info('Migration completed successfully!');
        return Command::SUCCESS;
    }
}
