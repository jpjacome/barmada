<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Helpers for building multi-tenant fixtures.
 *
 * Role flags are deliberately not mass-assignable on User, so every helper
 * sets them via forceFill() — exactly like the trusted production code paths.
 * Tenant-owned records are created with an explicit editor_id while no user
 * is authenticated, so the EditorScope global scope never interferes with
 * fixture setup.
 */
trait InteractsWithTenants
{
    protected function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin->refresh();
    }

    protected function makeEditor(): User
    {
        $editor = User::factory()->create();
        // Mirrors registration: an editor's tenant id is their own user id.
        $editor->forceFill(['is_editor' => true, 'editor_id' => $editor->id])->save();

        return $editor->refresh();
    }

    protected function makeStaff(User $editor): User
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_staff' => true, 'editor_id' => $editor->id])->save();

        return $staff->refresh();
    }

    /** A user with no role flags and no tenant. */
    protected function makeTenantlessUser(): User
    {
        return User::factory()->create()->refresh();
    }

    protected function makeTableFor(User $editor, array $attributes = []): Table
    {
        return Table::factory()->create(array_merge([
            'editor_id' => $editor->id,
        ], $attributes));
    }

    protected function makeProductFor(User $editor, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'editor_id' => $editor->id,
        ], $attributes));
    }

    protected function makeCategoryFor(User $editor, array $attributes = []): Category
    {
        return Category::factory()->create(array_merge([
            'editor_id' => $editor->id,
        ], $attributes));
    }

    protected function makeOrderFor(User $editor, array $attributes = []): Order
    {
        if (! array_key_exists('table_id', $attributes)) {
            $attributes['table_id'] = $this->makeTableFor($editor)->id;
        }

        return Order::factory()->create(array_merge([
            'editor_id' => $editor->id,
        ], $attributes));
    }

    protected function addItem(Order $order, Product $product, int $quantity = 1): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
            'is_paid' => false,
            'item_index' => 0,
        ]);
    }

    /**
     * An open table with a QR token and an open session, ready for the
     * guest order flow.
     *
     * @return array{0: Table, 1: TableSession}
     */
    protected function openTableWithSession(User $editor, array $tableAttributes = []): array
    {
        $table = Table::factory()->create(array_merge([
            'editor_id' => $editor->id,
            'status' => 'open',
            'unique_token' => (string) Str::uuid(),
        ], $tableAttributes));

        $session = TableSession::factory()->create([
            'table_id' => $table->id,
            'editor_id' => $editor->id,
            'status' => 'open',
            'unique_token' => $table->unique_token,
            'opened_at' => now(),
            'opened_by' => $editor->id,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return [$table, $session];
    }

    /** Approve the given IP (the test client's by default) on a session. */
    protected function approveDevice(TableSession $session, string $ip = '127.0.0.1'): TableSessionRequest
    {
        return TableSessionRequest::create([
            'table_session_id' => $session->id,
            'ip_address' => $ip,
            'status' => 'approved',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);
    }
}
