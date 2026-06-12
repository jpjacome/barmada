<?php

namespace Tests\Feature;

use App\Livewire\TablesList;
use App\Models\ClientInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Browser-print support (bill, ticket, QR sheet) and real client
 * invoice capture.
 */
class PrintAndInvoiceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_table_bill_renders_with_totals_and_is_tenant_bounded(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['currency_symbol' => '€'])->save();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['name' => 'Craft Beer', 'price' => 4.00]);
        $order = $this->makeOrderFor($editor, ['table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending']);
        $this->addItem($order, $product);
        $this->addItem($order, $product);

        $this->actingAs($editor)
            ->get('/tables/'.$table->id.'/bill')
            ->assertOk()
            ->assertSee('Craft Beer')
            ->assertSee('€8.00');

        // Another tenant cannot print this bill.
        $this->actingAs($this->makeEditor())
            ->get('/tables/'.$table->id.'/bill')
            ->assertNotFound();

        // Guests get sent to login.
        $this->post('/logout');
        $this->get('/tables/'.$table->id.'/bill')->assertRedirect('/login');
    }

    public function test_order_ticket_renders_with_note(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['name' => 'Nachos']);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
            'note' => 'extra cheese',
        ]);
        $this->addItem($order, $product);

        $this->actingAs($editor)
            ->get('/orders/'.$order->id.'/ticket')
            ->assertOk()
            ->assertSee('Nachos')
            ->assertSee('extra cheese');
    }

    public function test_qr_sheet_lists_active_tables_only(): void
    {
        $editor = $this->makeEditor();
        $this->makeTableFor($editor, ['table_number' => 1]);
        $archived = $this->makeTableFor($editor, ['table_number' => 2]);
        $archived->forceFill(['archived_at' => now()])->save();

        $response = $this->actingAs($editor)->get('/tables/qr-sheet')->assertOk();
        $response->assertSee('Table 1');
        $response->assertDontSee('Table 2');
    }

    public function test_invoice_details_are_captured_and_printed_on_the_bill(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);

        $this->actingAs($editor);
        Livewire::test(TablesList::class)
            ->call('openInvoiceModal', $table->id)
            ->assertSet('showInvoiceModal', true)
            ->set('invName', 'ACME S.A.')
            ->set('invTaxId', '1790012345001')
            ->set('invEmail', 'billing@acme.test')
            ->call('saveInvoice');

        $invoice = ClientInvoice::acrossEditors()->sole();
        $this->assertSame($session->id, $invoice->table_session_id);
        $this->assertSame($editor->id, $invoice->editor_id);

        $this->get('/tables/'.$table->id.'/bill')
            ->assertOk()
            ->assertSee('ACME S.A.')
            ->assertSee('1790012345001');
    }

    public function test_invoice_requires_an_open_session_and_required_fields(): void
    {
        $editor = $this->makeEditor();
        $closed = $this->makeTableFor($editor, ['status' => 'closed']);

        $this->actingAs($editor);
        Livewire::test(TablesList::class)
            ->call('openInvoiceModal', $closed->id)
            ->assertSet('showInvoiceModal', false)
            ->assertSet('showErrorModal', true);

        [$table] = $this->openTableWithSession($editor);
        Livewire::test(TablesList::class)
            ->call('openInvoiceModal', $table->id)
            ->set('invName', '')
            ->call('saveInvoice')
            ->assertHasErrors(['invName']);
        $this->assertSame(0, ClientInvoice::acrossEditors()->count());
    }
}
