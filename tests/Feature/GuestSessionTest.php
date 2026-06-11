<?php

namespace Tests\Feature;

use App\Livewire\AllOrdersList;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Guest "my table" page: session orders + running bill, and the
 * request-bill / call-waiter signals staff resolve from the board.
 */
class GuestSessionTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_approved_guest_sees_their_session_orders_and_running_bill(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['locale' => 'en', 'currency_symbol' => '€'])->save();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $product = $this->makeProductFor($editor, ['name' => 'Craft Beer', 'price' => 4.00]);
        $order = $this->makeOrderFor($editor, [
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
        ]);
        $this->addItem($order, $product);
        $paidItem = $this->addItem($order, $product);
        $paidItem->update(['is_paid' => true]);

        $response = $this->get('/order/'.$table->unique_token.'/session')->assertOk();

        $response->assertSee('Craft Beer');
        $response->assertSee('€8.00');   // table total
        $response->assertSee('€4.00');   // paid
        $response->assertSee('Request the bill');
        $response->assertSee('Call a waiter');
    }

    public function test_unapproved_devices_cannot_open_the_session_page(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        $this->get('/order/'.$table->unique_token.'/session')->assertForbidden();
        $this->post('/order/'.$table->unique_token.'/service', ['type' => 'bill'])->assertForbidden();
    }

    public function test_guest_can_request_the_bill_and_staff_resolve_it(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);

        $this->post('/order/'.$table->unique_token.'/service', ['type' => 'bill'])
            ->assertRedirect(route('order.session', ['unique_token' => $table->unique_token], absolute: false));

        $request = ServiceRequest::acrossEditors()->sole();
        $this->assertSame('bill', $request->type);
        $this->assertSame('pending', $request->status);
        $this->assertSame($editor->id, $request->editor_id);

        // Repeat taps don't pile up duplicates.
        $this->post('/order/'.$table->unique_token.'/service', ['type' => 'bill']);
        $this->assertSame(1, ServiceRequest::acrossEditors()->count());

        // Staff see it on the board and mark it done.
        $this->actingAs($editor);
        $component = Livewire::test(AllOrdersList::class);
        $component->assertSee('Bill requested');
        $component->call('markServiceRequestDone', $request->id);

        $request->refresh();
        $this->assertSame('done', $request->status);
        $this->assertNotNull($request->resolved_at);
    }

    public function test_other_tenants_cannot_resolve_a_service_request(): void
    {
        $editorA = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editorA);
        $request = ServiceRequest::create([
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'editor_id' => $editorA->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        $this->actingAs($this->makeEditor());
        Livewire::test(AllOrdersList::class)->call('markServiceRequestDone', $request->id);

        // Invisible through the tenant scope — still pending.
        $this->assertSame('pending', $request->fresh()->status);
    }
}
