<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CatalogManagementApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_product_create_with_photo_stores_a_randomized_safe_filename(): void
    {
        Storage::fake('public');
        $editor = $this->apiActingAs($this->makeEditor());

        $response = $this->post('/api/v1/products', [
            'name' => 'Pilsener Grande',
            'price' => 2.50,
            'photo' => UploadedFile::fake()->image('menu photo.PNG', 80, 80),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('product.name', 'Pilsener Grande')
            ->assertJsonPath('product.is_available', true);

        $photo = $response->json('product.photo');
        $this->assertNotNull($photo);
        $this->assertStringStartsWith('product-photos/', $photo);
        $this->assertStringNotContainsString('menu photo', $photo);
        $this->assertMatchesRegularExpression('/\.(png|jpg|gif|webp)$/', $photo);
        Storage::disk('public')->assertExists($photo);

        $this->assertSame($editor->id, Product::first()->editor_id);
    }

    public function test_svg_icon_upload_accepts_clean_svgs_and_rejects_active_content(): void
    {
        Storage::fake('public');
        $this->apiActingAs($this->makeEditor());

        // Clean SVG: accepted, stored under a random .svg name.
        $clean = UploadedFile::fake()->createWithContent(
            'icon.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="4"/></svg>'
        );
        $this->post('/api/v1/products', [
            'name' => 'Cerveza Artesanal',
            'price' => 4,
            'icon_type' => 'svg',
            'icon' => $clean,
        ], ['Accept' => 'application/json'])->assertCreated();

        // Scripted SVG: rejected, nothing persisted.
        $evil = UploadedFile::fake()->createWithContent(
            'evil.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>'
        );
        $this->post('/api/v1/products', [
            'name' => 'Producto Malicioso',
            'price' => 1,
            'icon_type' => 'svg',
            'icon' => $evil,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // PHP disguised with an .svg-less name: rejected by the extension gate.
        $php = UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');
        $this->post('/api/v1/products', [
            'name' => 'Otro Malicioso',
            'price' => 1,
            'icon_type' => 'svg',
            'icon' => $php,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(1, Product::count());
    }

    public function test_product_update_keeps_existing_media_when_none_uploaded(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $product = $this->makeProductFor($editor, [
            'name' => 'Mojito', 'price' => 5.50, 'photo' => 'product-photos/existing.jpg',
        ]);

        $this->postJson("/api/v1/products/{$product->id}", [
            'name' => 'Mojito Especial',
            'price' => 6.00,
        ])->assertOk()
            ->assertJsonPath('product.name', 'Mojito Especial')
            ->assertJsonPath('product.price', 6)
            ->assertJsonPath('product.photo', 'product-photos/existing.jpg');
    }

    public function test_foreign_category_assignment_is_dropped(): void
    {
        $editorA = $this->apiActingAs($this->makeEditor());
        $editorB = $this->makeEditor();
        $foreignCategory = $this->makeCategoryFor($editorB);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Empanadas',
            'price' => 3,
            'category_id' => $foreignCategory->id,
        ]);

        $response->assertCreated()->assertJsonPath('product.category', null);
    }

    public function test_product_management_is_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $productA = $this->makeProductFor($editorA);

        $this->apiActingAs($editorB);

        $this->postJson("/api/v1/products/{$productA->id}", ['name' => 'Robado', 'price' => 1])
            ->assertStatus(404);
        $this->deleteJson("/api/v1/products/{$productA->id}")->assertStatus(404);
        $this->assertNotNull($productA->fresh());
    }

    public function test_product_delete(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $product = $this->makeProductFor($editor);

        $this->deleteJson("/api/v1/products/{$product->id}")->assertOk();
        $this->assertNull(Product::find($product->id));
    }

    public function test_category_lifecycle_with_manual_ordering(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());

        // Create three categories: they take dense sort orders 1, 2, 3.
        $first = $this->postJson('/api/v1/categories', ['name' => 'Cervezas'])->assertCreated()->json('category');
        $second = $this->postJson('/api/v1/categories', ['name' => 'Cocteles'])->assertCreated()->json('category');
        $third = $this->postJson('/api/v1/categories', ['name' => 'Cocina'])->assertCreated()->json('category');
        $this->assertSame([1, 2, 3], [$first['sort_order'], $second['sort_order'], $third['sort_order']]);

        // Duplicate name within the tenant: rejected.
        $this->postJson('/api/v1/categories', ['name' => 'Cervezas'])->assertStatus(422);

        // Move "Cocina" up one step.
        $this->postJson("/api/v1/categories/{$third['id']}/move", ['direction' => 'up'])->assertOk();
        $this->assertSame(2, Category::find($third['id'])->sort_order);
        $this->assertSame(3, Category::find($second['id'])->sort_order);

        // Moving the top category up is a no-op (no wrap-around).
        $this->postJson("/api/v1/categories/{$first['id']}/move", ['direction' => 'up'])->assertOk();
        $this->assertSame(1, Category::find($first['id'])->sort_order);

        // Rename, uniqueness enforced against siblings.
        $this->patchJson("/api/v1/categories/{$third['id']}", ['name' => 'Cocteles'])->assertStatus(422);
        $this->patchJson("/api/v1/categories/{$third['id']}", ['name' => 'De la casa'])->assertOk();

        // Delete the middle one: ordering resequences densely.
        $this->deleteJson("/api/v1/categories/{$third['id']}")->assertOk();
        $this->assertSame([1, 2], Category::orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_same_category_name_is_fine_in_another_tenant(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $this->makeCategoryFor($editorA, ['name' => 'Cervezas']);

        $this->apiActingAs($editorB);

        $this->postJson('/api/v1/categories', ['name' => 'Cervezas'])->assertCreated();
    }

    public function test_categories_are_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $categoryA = $this->makeCategoryFor($editorA);

        $this->apiActingAs($editorB);

        $this->patchJson("/api/v1/categories/{$categoryA->id}", ['name' => 'Robada'])->assertStatus(404);
        $this->deleteJson("/api/v1/categories/{$categoryA->id}")->assertStatus(404);
        $this->postJson("/api/v1/categories/{$categoryA->id}/move", ['direction' => 'up'])->assertStatus(404);
        $this->assertCount(0, $this->getJson('/api/v1/categories')->json('categories'));
    }
}
