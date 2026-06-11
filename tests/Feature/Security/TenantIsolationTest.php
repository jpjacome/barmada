<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Model-layer tenant isolation [C-5 root cause, M-2].
 *
 * Every tenant-owned model carries the BelongsToEditor trait, whose
 * EditorScope global scope constrains queries to the authenticated user's
 * tenant. These tests pin that contract: editors and staff can only ever
 * see their own tenant's rows, admins span tenants, and tenant-less users
 * are denied by default.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_editor_queries_only_return_their_own_tenants_records(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        $tableA = $this->makeTableFor($editorA);
        $tableB = $this->makeTableFor($editorB);
        $this->makeProductFor($editorA);
        $this->makeProductFor($editorB);
        $this->makeCategoryFor($editorA);
        $this->makeCategoryFor($editorB);
        $orderA = $this->makeOrderFor($editorA, ['table_id' => $tableA->id]);
        $orderB = $this->makeOrderFor($editorB, ['table_id' => $tableB->id]);

        $this->actingAs($editorA);

        $this->assertSame([$orderA->id], Order::pluck('id')->all());
        $this->assertSame([$tableA->id], Table::pluck('id')->all());
        $this->assertCount(1, Product::all());
        $this->assertCount(1, Category::all());
        $this->assertSame($editorA->id, Product::first()->editor_id);

        // Direct lookups of the other tenant's rows resolve to nothing.
        $this->assertNull(Order::find($orderB->id));
        $this->assertNull(Table::find($tableB->id));
    }

    public function test_staff_queries_are_scoped_to_their_editors_tenant(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $staff = $this->makeStaff($editorA);

        $productA = $this->makeProductFor($editorA);
        $this->makeProductFor($editorB);

        $this->actingAs($staff);

        $this->assertSame([$productA->id], Product::pluck('id')->all());
    }

    public function test_admin_queries_span_all_tenants(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $this->makeOrderFor($editorA);
        $this->makeOrderFor($editorB);

        $this->actingAs($this->makeAdmin());

        $this->assertSame(2, Order::count());
    }

    public function test_tenantless_users_are_denied_by_default(): void
    {
        $editor = $this->makeEditor();
        $this->makeOrderFor($editor);
        $this->makeProductFor($editor);

        $this->actingAs($this->makeTenantlessUser());

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Table::count());
    }

    public function test_editor_id_is_assigned_automatically_on_create(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor);
        $product = Product::create(['name' => 'House Lager', 'price' => 4.50]);

        $this->assertSame($editor->id, $product->editor_id);
    }

    public function test_records_created_by_staff_belong_to_their_editors_tenant(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->actingAs($staff);
        $product = Product::create(['name' => 'Staff Special', 'price' => 6.00]);

        // The staff user's editor — never the staff user's own id.
        $this->assertSame($editor->id, $product->editor_id);
    }

    public function test_across_editors_escape_hatch_is_explicit_and_unscoped(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $this->makeOrderFor($editorA);
        $this->makeOrderFor($editorB);

        $this->actingAs($editorA);

        // Scoped by default…
        $this->assertSame(1, Order::count());
        // …cross-tenant access only through the named, greppable escape hatch
        // (callers must authorize first; admin screens use it).
        $this->assertSame(2, Order::acrossEditors()->count());
    }

    public function test_for_editor_with_null_tenant_matches_nothing(): void
    {
        $editor = $this->makeEditor();
        $this->makeProductFor($editor);

        // A null tenant must never widen a bounded lookup [batch 6].
        $this->assertSame(0, Product::forEditor(null)->count());
        $this->assertSame(1, Product::forEditor($editor->id)->count());
    }

    public function test_route_model_binding_resolves_within_the_callers_tenant_only(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $orderA = $this->makeOrderFor($editorA);
        $orderB = $this->makeOrderFor($editorB);

        // Implicit binding goes through the global scope: the other tenant's
        // order does not exist as far as this editor is concerned.
        $this->actingAs($editorA)
            ->get('/api/orders/'.$orderB->id)
            ->assertNotFound();

        $this->actingAs($editorA)
            ->get('/api/orders/'.$orderA->id)
            ->assertOk()
            ->assertJsonPath('id', $orderA->id);
    }
}
