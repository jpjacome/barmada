<?php

namespace Tests\Feature;

use App\Livewire\StaffList;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminTenantManagementTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_admin_can_delete_an_establishment_and_all_its_data(): void
    {
        $admin = $this->makeAdmin();
        $editor = $this->makeEditor();
        $bystander = $this->makeEditor();

        // Build a full venue: table + session + request + orders + catalog + staff.
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $category = $this->makeCategoryFor($editor);
        $product = $this->makeProductFor($editor, ['category_id' => $category->id]);
        $order = $this->makeOrderFor($editor, ['table_id' => $table->id, 'table_session_id' => $session->id]);
        $this->addItem($order, $product);
        $staff = $this->makeStaff($editor);
        ServiceRequest::create([
            'table_id' => $table->id, 'table_session_id' => $session->id,
            'editor_id' => $editor->id, 'type' => 'bill', 'status' => 'pending',
        ]);
        // Bystander tenant data that must survive.
        $bystanderTable = $this->makeTableFor($bystander);

        $this->actingAs($admin)
            ->delete('/admin/editors/'.$editor->id)
            ->assertRedirect(route('admin.editors', absolute: false));

        $this->assertNull(User::find($editor->id));
        $this->assertNull(User::find($staff->id));
        $this->assertSame(0, Table::acrossEditors()->where('editor_id', $editor->id)->count());
        $this->assertSame(0, Product::acrossEditors()->where('editor_id', $editor->id)->count());
        $this->assertSame(0, Category::acrossEditors()->where('editor_id', $editor->id)->count());
        $this->assertSame(0, Order::acrossEditors()->where('editor_id', $editor->id)->count());
        $this->assertSame(0, TableSession::acrossEditors()->where('editor_id', $editor->id)->count());
        $this->assertSame(0, TableSessionRequest::count());
        $this->assertSame(0, ServiceRequest::acrossEditors()->where('editor_id', $editor->id)->count());

        // The other tenant is untouched.
        $this->assertNotNull($bystanderTable->fresh());
        $this->assertNotNull(User::find($bystander->id));
    }

    public function test_admins_cannot_be_deleted_through_the_tenant_route(): void
    {
        $admin = $this->makeAdmin();
        $otherAdmin = $this->makeAdmin();

        $this->actingAs($admin)
            ->delete('/admin/editors/'.$otherAdmin->id)
            ->assertNotFound();

        $this->assertNotNull($otherAdmin->fresh());
    }

    public function test_editor_can_edit_a_staff_member(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->actingAs($editor);
        Livewire::test(StaffList::class)
            ->call('editStaff', $staff->id)
            ->assertSet('editMode', true)
            ->set('name', 'Renamed Person')
            ->set('email', 'renamed@bar.test')
            ->call('saveStaff');

        $staff->refresh();
        $this->assertSame('Renamed Person', $staff->name);
        $this->assertSame('renamed@bar.test', $staff->email);
    }

    public function test_editors_cannot_edit_other_tenants_staff(): void
    {
        $editorA = $this->makeEditor();
        $staffB = $this->makeStaff($this->makeEditor());

        $this->actingAs($editorA);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(StaffList::class)->call('editStaff', $staffB->id);
    }
}
