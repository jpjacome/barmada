<?php

namespace Tests\Feature\Api;

use App\Models\ApiDevice;
use App\Models\ClientInvoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminEstablishmentApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_establishments_are_admin_only(): void
    {
        $editor = $this->makeEditor();

        $this->apiActingAs($editor);
        $this->getJson('/api/v1/admin/establishments')->assertStatus(403);
        $this->deleteJson("/api/v1/admin/establishments/{$editor->id}", ['confirm' => true])
            ->assertStatus(403);
    }

    public function test_index_lists_editors_with_counts(): void
    {
        $admin = $this->makeAdmin();
        $editor = $this->makeEditor();
        $this->makeTableFor($editor);
        $this->makeProductFor($editor);
        $this->makeOrderFor($editor);

        $this->apiActingAs($admin);

        $rows = $this->getJson('/api/v1/admin/establishments')->assertOk()->json('establishments');

        $row = collect($rows)->firstWhere('id', $editor->id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['tables_count']); // makeOrderFor creates its own table too
        $this->assertSame(1, $row['products_count']);
        $this->assertSame(1, $row['orders_count']);
    }

    public function test_deletion_requires_explicit_confirmation(): void
    {
        $admin = $this->makeAdmin();
        $editor = $this->makeEditor();

        $this->apiActingAs($admin);

        $this->deleteJson("/api/v1/admin/establishments/{$editor->id}")->assertStatus(422);
        $this->assertNotNull(User::find($editor->id));
    }

    public function test_deletion_purges_the_whole_tenant_including_invoices_and_devices(): void
    {
        $admin = $this->makeAdmin();
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        // Tenant A: full venue with session, order, invoice, staff + device.
        [$tableA, $sessionA] = $this->openTableWithSession($editorA);
        $beer = $this->makeProductFor($editorA);
        $orderA = $this->makeOrderFor($editorA, [
            'table_id' => $tableA->id, 'table_session_id' => $sessionA->id,
        ]);
        $this->addItem($orderA, $beer);
        ClientInvoice::create([
            'table_session_id' => $sessionA->id,
            'table_id' => $tableA->id,
            'editor_id' => $editorA->id,
            'name' => 'Juan Pérez',
            'tax_id' => '1712345678001',
        ]);
        ServiceRequest::create([
            'table_id' => $tableA->id, 'table_session_id' => $sessionA->id,
            'editor_id' => $editorA->id, 'type' => 'bill', 'status' => 'pending',
        ]);
        $staffA = $this->makeStaff($editorA);
        ApiDevice::create(['user_id' => $staffA->id, 'device_uuid' => 'staff-phone']);

        // Tenant B: must remain untouched.
        $tableB = $this->makeTableFor($editorB);

        $this->apiActingAs($admin);

        $this->deleteJson("/api/v1/admin/establishments/{$editorA->id}", ['confirm' => true])
            ->assertOk();

        // Everything of tenant A is gone — including the PII-bearing
        // client invoices (a leak the original web deletion missed) and
        // the staff member's push device (FK cascade).
        $this->assertNull(User::find($editorA->id));
        $this->assertNull(User::find($staffA->id));
        $this->assertSame(0, Table::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(0, TableSession::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(0, Order::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(0, Product::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(0, ClientInvoice::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(0, ServiceRequest::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(0, ApiDevice::where('user_id', $staffA->id)->count());

        // Tenant B untouched.
        $this->assertNotNull(User::find($editorB->id));
        $this->assertNotNull(Table::acrossEditors()->find($tableB->id));
    }

    public function test_admin_accounts_cannot_be_deleted_as_establishments(): void
    {
        $admin = $this->makeAdmin();
        $otherAdmin = $this->makeAdmin();

        $this->apiActingAs($admin);

        $this->deleteJson("/api/v1/admin/establishments/{$otherAdmin->id}", ['confirm' => true])
            ->assertStatus(404);
        $this->assertNotNull(User::find($otherAdmin->id));
    }
}
