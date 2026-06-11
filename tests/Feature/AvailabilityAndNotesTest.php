<?php

namespace Tests\Feature;

use App\Livewire\ProductsList;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Product availability ("86") and order notes.
 */
class AvailabilityAndNotesTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_sold_out_products_cannot_be_ordered_by_guests(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $product = $this->makeProductFor($editor, ['name' => 'Craft Beer', 'is_available' => false]);

        // Menu shows the item as sold out, with no quantity controls.
        $editor->forceFill(['locale' => 'en'])->save();
        $response = $this->get('/order/'.$table->unique_token)->assertOk();
        $response->assertSee('Craft Beer');
        $response->assertSee('Sold out');
        $response->assertDontSee('name="products['.$product->id.']"', false);

        // And the server rejects it even if posted directly.
        $this->post('/order/'.$table->unique_token, [
            'products' => [$product->id => 1],
        ])->assertStatus(422);

        $this->assertSame(0, Order::acrossEditors()->count());
    }

    public function test_staff_toggle_availability_from_the_products_page(): void
    {
        $editor = $this->makeEditor();
        $product = $this->makeProductFor($editor, ['is_available' => true]);

        $this->actingAs($editor);
        Livewire::test(ProductsList::class)->call('toggleAvailability', $product->id);
        $this->assertFalse($product->fresh()->is_available);

        Livewire::test(ProductsList::class)->call('toggleAvailability', $product->id);
        $this->assertTrue($product->fresh()->is_available);
    }

    public function test_guest_order_note_is_stored_and_sanitized(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $product = $this->makeProductFor($editor);

        $this->post('/order/'.$table->unique_token, [
            'products' => [$product->id => 1],
            'note' => '  No ice please <script>alert(1)</script> ',
        ])->assertRedirect();

        $order = Order::acrossEditors()->sole();
        $this->assertStringContainsString('No ice please', $order->note);
        $this->assertStringNotContainsString('<script>', $order->note);
    }

    public function test_staff_order_records_note_and_creator(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor);

        $this->actingAs($editor)->post('/order', [
            'table_id' => $table->id,
            'products' => [$product->id => 1],
            'note' => 'VIP table',
        ])->assertRedirect();

        $order = Order::acrossEditors()->sole();
        $this->assertSame('VIP table', $order->note);
        $this->assertSame($editor->id, $order->created_by);
    }
}
