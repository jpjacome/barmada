<?php

namespace Tests\Feature;

use App\Livewire\TablesList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Paying a table in full must NOT auto-close it. [F-11]
 *
 * Closing a session kills the QR token and ejects seated guests — fatal
 * for the (very common) pay-per-round pattern. Closing is now always an
 * explicit staff action.
 */
class TablePaymentBehaviorTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_full_payment_keeps_the_table_open(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['price' => 5.00]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
        ]);
        $this->addItem($order, $product);
        $token = $table->unique_token;

        $this->actingAs($editor);
        Livewire::test(TablesList::class)
            ->call('viewTableOrders', $table->id)
            ->call('toggleAllTableItems');

        $table->refresh();
        $this->assertSame('open', $table->status);
        $this->assertSame($token, $table->unique_token);
        $this->assertTrue($order->items()->get()->every(fn ($item) => $item->is_paid));
    }

    public function test_item_level_payment_keeps_the_table_open(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['price' => 5.00]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
        ]);
        $item = $this->addItem($order, $product);

        $this->actingAs($editor);
        Livewire::test(TablesList::class)
            ->call('viewTableOrders', $table->id)
            ->call('selectItem', $order->id, $product->id, $item->item_index);

        $this->assertTrue($item->fresh()->is_paid);
        $this->assertSame('open', $table->fresh()->status);
    }

    public function test_pay_and_close_still_closes_the_table_explicitly(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['price' => 5.00]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
        ]);
        $this->addItem($order, $product);

        $this->actingAs($editor);
        Livewire::test(TablesList::class)
            ->call('viewTableOrders', $table->id)
            ->call('payAndCloseTable');

        $table->refresh();
        $this->assertSame('closed', $table->status);
        $this->assertNull($table->unique_token);
        $this->assertTrue($order->items()->get()->every(fn ($item) => $item->is_paid));
    }
}
