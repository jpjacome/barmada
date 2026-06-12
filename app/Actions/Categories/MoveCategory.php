<?php

namespace App\Actions\Categories;

use App\Exceptions\DomainActionException;
use App\Models\Category;

/**
 * Moves a category one step up or down in the tenant's manual ordering
 * by swapping sort_order with its adjacent sibling. Edges are no-ops
 * (no wrap-around). Bounded to the category's own tenant.
 */
class MoveCategory
{
    /**
     * @throws DomainActionException
     */
    public function handle(Category $category, string $direction): Category
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new DomainActionException(__('Unknown move direction.'));
        }

        $neighbor = $direction === 'up'
            ? Category::forEditor($category->editor_id)
                ->where('sort_order', '<', $category->sort_order)
                ->orderBy('sort_order', 'desc')
                ->first()
            : Category::forEditor($category->editor_id)
                ->where('sort_order', '>', $category->sort_order)
                ->orderBy('sort_order', 'asc')
                ->first();

        if ($neighbor) {
            $tempOrder = $category->sort_order;
            $category->sort_order = $neighbor->sort_order;
            $neighbor->sort_order = $tempOrder;

            $category->save();
            $neighbor->save();
        }

        return $category->refresh();
    }
}
