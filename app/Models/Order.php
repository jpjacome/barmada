<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'total_amount',
        'amount_paid',
        'amount_left',
        'is_grouped',
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

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_left' => 'decimal:2',
        'is_grouped' => 'boolean',
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
        return $this->items()->sum('quantity');
    }
    
    /**
     * Get the total price of the order.
     */
    public function getTotalPriceAttribute()
    {
        return $this->items()->sum(function($item) {
            return $item->quantity * $item->price;
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
} 