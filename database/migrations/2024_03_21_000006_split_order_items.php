<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\OrderItem;

return new class extends Migration
{
    public function up()
    {
        // Get all order items with quantity > 1
        $items = OrderItem::where('quantity', '>', 1)->get();
        
        foreach ($items as $item) {
            // Create individual items for each unit
            for ($i = 1; $i < $item->quantity; $i++) {
                OrderItem::create([
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => 1,
                    'price' => $item->price,
                    'is_paid' => $item->is_paid,
                    'item_index' => $item->item_index + $i
                ]);
            }
            
            // Update the original item to have quantity 1
            $item->update(['quantity' => 1]);
        }
    }

    public function down()
    {
        // This migration cannot be reversed as it would require merging items
        // which could lead to data loss
    }
}; 