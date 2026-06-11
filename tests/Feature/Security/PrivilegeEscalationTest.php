<?php

namespace Tests\Feature\Security;

use App\Livewire\AllOrdersList;
use App\Livewire\StaffList;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Privilege-escalation and destructive-action regressions
 * [C-3, C-4, M mass assignment, L impersonation].
 */
class PrivilegeEscalationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    // ── C-3: staff tooling account takeover ────────────────────────────

    public function test_editor_cannot_reset_credentials_of_foreign_staff(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $foreignStaff = $this->makeStaff($editorB);
        $originalHash = $foreignStaff->password;

        $this->actingAs($editorA);

        try {
            Livewire::test(StaffList::class)
                ->set('editMode', true)
                ->set('staffId', $foreignStaff->id)
                ->set('name', 'Hijacked')
                ->set('email', 'hijacked@example.com')
                ->set('password', 'attacker-password')
                ->call('saveStaff');

            $this->fail('Expected the foreign staff lookup to fail.');
        } catch (ModelNotFoundException) {
            // Lookup is bounded to the editor's own staff before any write.
        }

        $foreignStaff->refresh();
        $this->assertSame($originalHash, $foreignStaff->password);
        $this->assertNotSame('hijacked@example.com', $foreignStaff->email);
    }

    public function test_admin_and_editor_accounts_are_unreachable_via_staff_tooling(): void
    {
        // [C-3] The original exploit reset the ADMIN's password from an
        // editor session. Admin/editor accounts must never resolve through
        // staff management, even for ids the attacker knows.
        $editor = $this->makeEditor();
        $admin = $this->makeAdmin();
        $adminHash = $admin->password;

        $this->actingAs($editor);

        try {
            Livewire::test(StaffList::class)
                ->set('editMode', true)
                ->set('staffId', $admin->id)
                ->set('name', 'Owned Admin')
                ->set('email', 'owned-admin@example.com')
                ->set('password', 'attacker-password')
                ->call('saveStaff');

            $this->fail('Expected the admin account to be unreachable.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame($adminHash, $admin->fresh()->password);
    }

    public function test_editor_manages_their_own_staff(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->actingAs($editor);

        Livewire::test(StaffList::class)
            ->set('editMode', true)
            ->set('staffId', $staff->id)
            ->set('name', 'Renamed Staffer')
            ->set('email', $staff->email)
            ->set('password', 'new-secret-pass')
            ->call('saveStaff');

        $staff->refresh();
        $this->assertSame('Renamed Staffer', $staff->name);
        $this->assertTrue(Hash::check('new-secret-pass', $staff->password));
    }

    public function test_staff_management_is_editor_only(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->actingAs($staff);

        // Livewire surfaces the mount-time authorization failure as a 403.
        Livewire::test(StaffList::class)->assertForbidden();
    }

    // ── C-4: cross-tenant bulk deletion ────────────────────────────────

    public function test_bulk_clear_only_deletes_the_callers_tenant_orders(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        $orderA = $this->makeOrderFor($editorA);
        $this->addItem($orderA, $this->makeProductFor($editorA));
        $orderB = $this->makeOrderFor($editorB);
        $this->addItem($orderB, $this->makeProductFor($editorB));

        // [C-4] deleteAllOrders() used to truncate every tenant's orders.
        $this->actingAs($editorA);
        Livewire::test(AllOrdersList::class)->call('deleteAllOrders');

        $this->assertSame(0, Order::acrossEditors()->where('editor_id', $editorA->id)->count());
        $this->assertSame(1, Order::acrossEditors()->where('editor_id', $editorB->id)->count());
        $this->assertSame(1, OrderItem::where('order_id', $orderB->id)->count());
    }

    public function test_staff_cannot_bulk_clear_orders(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor);

        $this->actingAs($this->makeStaff($editor));

        // Livewire surfaces the policy denial as a 403 on the action call.
        Livewire::test(AllOrdersList::class)
            ->call('deleteAllOrders')
            ->assertForbidden();

        $this->assertSame(1, Order::acrossEditors()->count());
    }

    // ── M: mass assignment ─────────────────────────────────────────────

    public function test_privilege_flags_are_not_mass_assignable(): void
    {
        $user = User::create([
            'username' => 'sneaky',
            'first_name' => 'Sneaky',
            'last_name' => 'User',
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_editor' => true,
            'is_staff' => true,
        ]);

        $user->refresh();
        $this->assertFalse((bool) $user->is_admin);
        $this->assertFalse((bool) $user->is_editor);
        $this->assertFalse((bool) $user->is_staff);
    }

    public function test_registration_cannot_be_escalated_with_injected_role_flags(): void
    {
        $response = $this->post('/register', [
            'username' => 'newvenue',
            'email' => 'venue@example.com',
            'business_name' => 'New Venue',
            'table_count' => 2,
            'password' => 'password',
            'password_confirmation' => 'password',
            // Injected fields an attacker would add:
            'is_admin' => 1,
            'is_staff' => 1,
            'editor_id' => 1,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'venue@example.com')->firstOrFail();
        $this->assertFalse((bool) $user->is_admin);
        $this->assertFalse((bool) $user->is_staff);
        $this->assertTrue((bool) $user->is_editor);
        // Registration binds the editor to their own tenant, not an injected one.
        $this->assertSame($user->id, $user->editor_id);
        $this->assertSame(2, Table::acrossEditors()->where('editor_id', $user->id)->count());
    }

    public function test_table_primary_key_is_not_mass_assignable(): void
    {
        $editor = $this->makeEditor();

        $table = Table::create([
            'id' => 999999,
            'editor_id' => $editor->id,
            'table_number' => 1,
        ]);

        $this->assertNotSame(999999, $table->id);
    }

    public function test_table_numbers_are_unique_per_tenant_not_globally(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        Table::create(['editor_id' => $editorA->id, 'table_number' => 5]);
        // Same number in another tenant is fine.
        Table::create(['editor_id' => $editorB->id, 'table_number' => 5]);
        $this->assertSame(2, Table::acrossEditors()->where('table_number', 5)->count());

        // Duplicate within the same tenant violates the composite unique index.
        $this->expectException(QueryException::class);
        Table::create(['editor_id' => $editorA->id, 'table_number' => 5]);
    }

    // ── L: impersonation ───────────────────────────────────────────────

    public function test_leaving_impersonation_requires_an_active_impersonation(): void
    {
        $editor = $this->makeEditor();

        // Without a recorded impersonation there is nothing to "return" to;
        // the old behavior logged the caller out (or worse).
        $this->actingAs($editor)
            ->post('/admin/impersonate/leave')
            ->assertForbidden();

        $this->assertAuthenticatedAs($editor);
    }

    public function test_admin_can_impersonate_an_editor_and_return(): void
    {
        $admin = $this->makeAdmin();
        $editor = $this->makeEditor();

        $this->actingAs($admin)
            ->post('/admin/impersonate/'.$editor->id)
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($editor);

        $this->post('/admin/impersonate/leave')
            ->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admins_cannot_impersonate(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        $this->actingAs($editorA)->post('/admin/impersonate/'.$editorB->id);

        $this->assertAuthenticatedAs($editorA);
    }

    public function test_only_editors_can_be_impersonated(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff($this->makeEditor());

        $this->actingAs($admin)
            ->post('/admin/impersonate/'.$staff->id)
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }
}
