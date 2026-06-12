<?php

namespace App\Actions\Products;

use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\SafeSvg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Creates or updates a catalog product — one code path for the web
 * product form and the API.
 *
 * Upload posture (unchanged from the web): icons must be genuine,
 * script-free SVGs; photos must be real raster images; both are stored
 * under RANDOM names with forced safe extensions so a crafted original
 * filename can never land an executable file on the public disk.
 *
 * Shape/size validation (mimes, max kb) belongs to the calling boundary;
 * this action enforces the security gates and persists.
 */
class SaveProduct
{
    /**
     * @param  array{name: string, price: mixed, icon_type: string, bootstrap_icon?: ?string, icon_value_fallback?: ?string, category_id?: mixed, description?: ?string, photo_fallback?: ?string}  $data
     *
     * @throws DomainActionException
     */
    public function handle(
        User $actor,
        array $data,
        ?UploadedFile $svgIcon = null,
        ?UploadedFile $photo = null,
        ?Product $product = null,
    ): Product {
        $iconType = $data['icon_type'] === 'svg' ? 'svg' : 'bootstrap';

        if ($iconType === 'bootstrap') {
            $iconValue = $data['bootstrap_icon'] ?? 'bi-box';
        } elseif ($svgIcon) {
            $original = strtolower((string) $svgIcon->getClientOriginalExtension());
            $contents = @file_get_contents($svgIcon->getRealPath());

            if ($original !== 'svg' || $contents === false || ! SafeSvg::check($contents)) {
                throw new DomainActionException(
                    __('The icon must be a valid SVG with no scripts or embedded content.')
                );
            }

            $iconValue = $svgIcon->storeAs('product-icons', Str::random(40).'.svg', 'public');
        } else {
            // Retain the existing icon when no new file is uploaded.
            $iconValue = $data['icon_value_fallback'] ?? null;
        }

        if ($photo) {
            $photoExtensionMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $photoExt = $photoExtensionMap[$photo->getMimeType()] ?? 'jpg';
            $photoPath = $photo->storeAs('product-photos', Str::random(40).'.'.$photoExt, 'public');
        } else {
            $photoPath = $data['photo_fallback'] ?? null;
        }

        // Category assignment must resolve within the caller's tenant
        // (EditorScope bounds the lookup for non-admins).
        $categoryId = $data['category_id'] ?? null;
        if ($categoryId !== null && $categoryId !== '' && ! Category::find($categoryId)) {
            $categoryId = null;
        }

        $attributes = [
            'name' => $data['name'],
            'price' => $data['price'],
            'icon_type' => $iconType,
            'icon_value' => $iconValue,
            'category_id' => $categoryId ?: null,
            'description' => $data['description'] ?? null,
            'photo' => $photoPath,
        ];

        if ($product) {
            $product->update($attributes);

            return $product->refresh();
        }

        if ($actor->is_admin) {
            // Admins create under their own tenant (web parity);
            // non-admins get the tenant from BelongsToEditor.
            $attributes['editor_id'] = $actor->id;
        }

        // Refresh so database defaults (is_available) are present.
        return Product::create($attributes)->refresh();
    }
}
