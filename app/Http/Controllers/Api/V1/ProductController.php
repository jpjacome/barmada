<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\SaveProduct;
use App\Actions\Products\ToggleProductAvailability;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use AuthorizesRequests;

    private const RULES = [
        'name' => ['required', 'min:3', 'max:255', 'regex:/^[^<>]*$/'],
        'price' => 'required|numeric|min:0.01',
        'icon_type' => 'nullable|in:bootstrap,svg',
        'bootstrap_icon' => ['nullable', 'regex:/^[a-z0-9 -]+$/i'],
        'icon' => 'nullable|file|max:1024',
        'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:1024'],
        'category_id' => 'nullable|integer',
        'description' => 'nullable|string|max:1000',
    ];

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
     * Create a catalog product. Multipart: optional "photo" (raster
     * image) and "icon" (script-free SVG when icon_type=svg) uploads —
     * stored under random names with forced safe extensions, exactly
     * like the web form.
     */
    public function store(Request $request, SaveProduct $saveProduct)
    {
        $validated = $request->validate(self::RULES);

        $this->authorize('create', Product::class);

        $product = $saveProduct->handle(
            $request->user(),
            [
                'name' => $validated['name'],
                'price' => $validated['price'],
                'icon_type' => $validated['icon_type'] ?? 'bootstrap',
                'bootstrap_icon' => $validated['bootstrap_icon'] ?? 'bi-box',
                'category_id' => $validated['category_id'] ?? null,
                'description' => $validated['description'] ?? null,
            ],
            $request->file('icon'),
            $request->file('photo'),
        );

        return response()->json(
            ['product' => $this->productRow($product->load('category'))],
            201
        );
    }

    /**
     * Update a product. POST (not PATCH) so multipart uploads work from
     * mobile HTTP clients.
     */
    public function update(Request $request, Product $product, SaveProduct $saveProduct)
    {
        $validated = $request->validate(self::RULES);

        $this->authorize('update', $product);

        $saveProduct->handle(
            $request->user(),
            [
                'name' => $validated['name'],
                'price' => $validated['price'],
                'icon_type' => $validated['icon_type'] ?? $product->icon_type ?? 'bootstrap',
                'bootstrap_icon' => $validated['bootstrap_icon'] ?? ($product->icon_type === 'bootstrap' ? $product->icon_value : 'bi-box'),
                'icon_value_fallback' => $product->icon_value,
                'category_id' => array_key_exists('category_id', $validated) ? $validated['category_id'] : $product->category_id,
                'description' => $validated['description'] ?? $product->description,
                'photo_fallback' => $product->photo,
            ],
            $request->file('icon'),
            $request->file('photo'),
            $product,
        );

        return response()->json(['product' => $this->productRow($product->refresh()->load('category'))]);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['message' => __('Product deleted.')]);
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
