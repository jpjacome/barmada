<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'net_price',
        'tax_amount',
        'tax_code',
        'tax_rate_bp',
        'is_paid',
        'paid_at',
        'paid_by',
        'payment_method',
        'item_index'
    ];

    /**
     * price is the GROSS amount owed for this unit (tax included) and is
     * decimal(8,2): cast so it arrives the same way under MySQL (numeric
     * string) and SQLite (float). net_price + tax_amount = price. Sum with
     * App\Support\Money.
     */
    protected $casts = [
        'is_paid' => 'boolean',
        'price' => 'decimal:2',
        'net_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate_bp' => 'integer',
        'paid_at' => 'datetime',
    ];

    public const PAYMENT_METHODS = ['cash', 'card', 'transfer', 'other'];

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
