<?php

namespace App\Exceptions;

use App\Models\Product;

/**
 * An ordered product is currently 86'd (sold out).
 */
class ProductUnavailableException extends DomainActionException
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct(__('“:name” is sold out.', ['name' => $product->name]));
    }
}
