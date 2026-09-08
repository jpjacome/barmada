<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a product's recipe: selling one unit of the product
 * consumes quantity_per_unit of the stock item.
 */
class ProductComponent extends Model
{
    protected $fillable = ['product_id', 'stock_item_id', 'quantity_per_unit'];

    protected $casts = ['quantity_per_unit' => 'decimal:4'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }
}
