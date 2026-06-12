<?php

namespace App\Actions\Products;

use App\Models\Product;

/**
 * One-tap 86: flips a product's availability. Unavailable products are
 * hidden from the guest menu and rejected server-side at order time.
 */
class ToggleProductAvailability
{
    public function handle(Product $product): Product
    {
        $product->is_available = ! $product->is_available;
        $product->save();

        return $product;
    }
}
