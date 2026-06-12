<?php

namespace App\Actions\Categories;

use App\Models\Category;

/**
 * Creates a category at the end of the tenant's manual ordering.
 * Name validation (incl. per-tenant uniqueness) belongs to the calling
 * boundary.
 */
class CreateCategory
{
    public function handle(int $editorId, string $name): Category
    {
        $maxOrder = Category::forEditor($editorId)->max('sort_order') ?? 0;

        return Category::create([
            'name' => $name,
            'editor_id' => $editorId,
            'sort_order' => $maxOrder + 1,
        ]);
    }
}
