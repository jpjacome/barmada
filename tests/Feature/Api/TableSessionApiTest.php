<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\ClientInvoice;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TableSessionApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_opening_a_table_creates_exactly_one_session_and_rotates_the_token(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed']);

        $this->apiActingAs($editor);

        $response = $this->postJson("/api/v1/tables/{$table->id}/open");

        $response->assertOk()
            ->assertJsonPath('table.status', 'open');

        $table->refresh();
        $this->assertNotNull($table->unique_token);

        // Regression: the legacy web path used to create a duplicate
        // session alongside the model hook's one.
        $this->assertSame(1, TableSession::where('table_id', $table->id)->count());

        $session = TableSession::where('table_id', $table->id)->first();
        $this->assertSame('open', $session->status);
        $this->assertSame($table->unique_token, $session->unique_token);
        $this->assertSame($response->json('session.id'), $session->id);
    }

    public function test_opening_an_open_table_is_rejected(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/tables/{$table->id}/open")->assertStatus(422);
    }

    public function test_item_payment_ticking_updates_totals_and_auto_delivers(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor, ['price' => 2.50]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
        ]);
        $itemA = $this->addItem($order, $beer);
        $itemB = $this->addItem($order, $beer);
        $itemB->update(['item_index' => 1]);

        $this->apiActingAs($editor);

        // Tick the first unit.
        $this->postJson("/api/v1/orders/{$order->id}/items/toggle-paid", [
            'product_id' => $beer->id,
            'item_index' => 0,
        ])->assertOk()->assertJsonPath('data.paid', 2.5)->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('activity_logs', [
            'type' => 'payment',
            'order_id' => $order->id,
            'editor_id' => $editor->id,
        ]);

        // Tick the second unit — fully paid auto-delivers.
        $this->postJson("/api/v1/orders/{$order->id}/items/toggle-paid", [
            'product_id' => $beer->id,
            'item_index' => 1,
        ])->assertOk()->assertJsonPath('data.left', 0)->assertJsonPath('data.status', 'delivered');

        // Session totals agree.
        $this->getJson("/api/v1/tables/{$table->id}/session")
            ->assertOk()
            ->assertJsonPath('totals.total', 5)
            ->assertJsonPath('totals.paid', 5)
            ->assertJsonPath('totals.left', 0);
    }

    public function test_unknown_item_returns_404(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/orders/{$order->id}/items/toggle-paid", [
            'product_id' => 999,
            'item_index' => 0,
        ])->assertStatus(404);
    }

    public function test_settling_an_order_pays_everything_and_logs(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor, ['price' => 4]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
        ]);
        $this->addItem($order, $beer);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/orders/{$order->id}/settle")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.left', 0);

        $this->assertSame(1, ActivityLog::where('type', 'payment')->where('order_id', $order->id)->count());
    }

    public function test_cancelled_orders_are_excluded_from_the_session_bill(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor, ['price' => 10]);

        $kept = $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending',
        ]);
        $this->addItem($kept, $beer);

        $cancelled = $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'cancelled',
        ]);
        $this->addItem($cancelled, $beer);

        $this->apiActingAs($editor);

        $response = $this->getJson("/api/v1/tables/{$table->id}/session");

        $response->assertOk()->assertJsonPath('totals.total', 10);
        $this->assertCount(1, $response->json('orders'));
    }

    public function test_close_requires_full_payment_unless_settling(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor, ['price' => 3]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending',
        ]);
        $this->addItem($order, $beer);

        $this->apiActingAs($editor);

        // Unpaid: refuse.
        $this->postJson("/api/v1/tables/{$table->id}/close")->assertStatus(422);

        // Settle-and-close in one step.
        $this->postJson("/api/v1/tables/{$table->id}/close", ['settle' => true])
            ->assertOk()
            ->assertJsonPath('table.status', 'closed');

        $table->refresh();
        $this->assertNull($table->unique_token);
        $this->assertSame('closed', TableSession::find($session->id)->status);
        $this->assertFalse($order->refresh()->items->contains(fn ($item) => ! $item->is_paid));
    }

    public function test_archive_requires_a_closed_table_and_restore_brings_it_back(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/tables/{$table->id}/archive")->assertStatus(422);

        $this->postJson("/api/v1/tables/{$table->id}/close", ['settle' => true])->assertOk();
        $this->postJson("/api/v1/tables/{$table->id}/archive")
            ->assertOk();
        $this->assertNotNull($table->refresh()->archived_at);

        // Archived tables leave the default grid…
        $grid = $this->getJson('/api/v1/tables')->json('tables');
        $this->assertNotContains($table->id, collect($grid)->pluck('id')->all());

        // …and restore returns them.
        $this->postJson("/api/v1/tables/{$table->id}/restore")->assertOk();
        $this->assertNull($table->refresh()->archived_at);
    }

    public function test_client_invoice_capture_validates_and_persists(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/tables/{$table->id}/invoice", ['name' => 'Juan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tax_id');

        $this->postJson("/api/v1/tables/{$table->id}/invoice", [
            'name' => 'Juan Pérez',
            'tax_id' => '1712345678001',
        ])->assertOk()->assertJsonPath('invoice.name', 'Juan Pérez');

        $this->assertDatabaseHas('client_invoices', [
            'table_session_id' => $session->id,
            'tax_id' => '1712345678001',
        ]);

        // Visible on the session payload.
        $this->getJson("/api/v1/tables/{$table->id}/session")
            ->assertJsonPath('invoice.tax_id', '1712345678001');

        // Upsert, not duplicate.
        $this->postJson("/api/v1/tables/{$table->id}/invoice", [
            'name' => 'Juan P. Pérez',
            'tax_id' => '1712345678001',
        ])->assertOk();
        $this->assertSame(1, ClientInvoice::count());
    }
}
