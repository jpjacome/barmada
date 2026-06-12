<?php

namespace App\Actions\Categories;

use App\Models\Category;

/**
 * Deletes a category and resequences the tenant's manual ordering to
 * stay dense (1, 2, 3, …). Products keep a dangling category_id of null
 * via the schema's nullOnDelete behavior or render as uncategorized.
 */
class DeleteCategory
{
    public function handle(Category $category): void
    {
        $editorId = $category->editor_id;
        $category->delete();

        $i = 1;
        foreach (Category::forEditor($editorId)->orderBy('sort_order')->get() as $sibling) {
            if ((int) $sibling->sort_order !== $i) {
                $sibling->sort_order = $i;
                $sibling->save();
            }
            $i++;
        }
    }
}
