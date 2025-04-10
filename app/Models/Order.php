<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'table_id',
        'status',
        'product1_qty',
        'product2_qty',
        'product3_qty',
        'product4_qty',
        'product5_qty',
        'product6_qty',
        'product7_qty',
        'product8_qty',
        'product9_qty',
    ];

    /**
     * Get the table that owns the order.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }
    
    /**
     * Get the total number of items in the order.
     */
    public function getTotalItemsAttribute()
    {
        $total = 0;
        for ($i = 1; $i <= 9; $i++) {
            $total += $this->{"product{$i}_qty"} ?? 0;
        }
        return $total;
    }
    
    /**
     * Get the total price of the order.
     */
    public function getTotalPriceAttribute()
    {
        $total = 0;
        $products = Product::all()->keyBy('id');
        
        for ($i = 1; $i <= 9; $i++) {
            $qty = $this->{"product{$i}_qty"} ?? 0;
            if ($qty > 0 && isset($products[$i])) {
                $total += $qty * $products[$i]->price;
            }
        }
        
        return $total;
    }
} 