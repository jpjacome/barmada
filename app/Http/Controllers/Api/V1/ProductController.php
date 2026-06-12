<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\ToggleProductAvailability;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * The catalog as live service needs it: names, prices, categories and
     * availability. Bounded by EditorScope. Full catalog management (CRUD,
     * photos, category ordering) arrives in the next PR of the series.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'available' => 'nullable|boolean',
            'category_id' => 'nullable|integer',
        ]);

        $query = Product::with('category')->orderBy('name');

        if (array_key_exists('available', $validated) && $validated['available'] !== null) {
            $query->where('is_available', (bool) $validated['available']);
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', (int) $validated['category_id']);
        }

        return response()->json([
            'products' => $query->get()->map(fn (Product $product) => $this->productRow($product)),
        ]);
    }

    /**
     * One-tap 86: hide a product from the guest menu (and reject it
     * server-side at order time) or bring it back.
     */
    public function toggleAvailability(Product $product, ToggleProductAvailability $toggle)
    {
        $this->authorize('update', $product);

        $toggle->handle($product);

        return response()->json(['product' => $this->productRow($product->refresh()->load('category'))]);
    }

    private function productRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'description' => $product->description,
            'is_available' => (bool) $product->is_available,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'photo' => $product->photo,
        ];
    }
}
