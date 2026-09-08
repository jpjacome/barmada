<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrder;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TaxRateOverride;
use App\Models\User;
use App\Support\TableBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ecuador's rules, end to end: IVA 15% by default, prices inclusive by
 * default, per-product overrides, date-bounded rate remaps (the 8%
 * tourism days), and the optional 10% servicio on the pre-tax base.
 */
class TaxModelTest extends TestCase
{
    use RefreshDatabase;

    private function venue(array $overrides = []): User
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $venue->forceFill($overrides)->save();

        return $venue->refresh();
    }

    private function openTable(User $venue): array
    {
        $table = Table::create(['editor_id' => $venue->id, 'table_number' => 1, 'status' => 'open']);
        $session = TableSession::create([
            'table_id' => $table->id, 'session_number' => 1, 'date' => now()->toDateString(),
            'unique_token' => (string) Str::uuid(), 'status' => 'open', 'opened_at' => now(),
            'opened_by' => $venue->id, 'editor_id' => $venue->id,
        ]);

        return [$table, $session];
    }

    public function test_orders_snapshot_iva_inside_inclusive_prices(): void
    {
        $venue = $this->venue();
        [$table, $session] = $this->openTable($venue);
        $beer = Product::create(['name' => 'Pilsener', 'price' => 4.50, 'editor_id' => $venue->id, 'is_available' => true]);

        $order = app(CreateOrder::class)->handle($table, $session, [$beer->id => 2]);

        $item = $order->items->first();
        $this->assertSame('4', $item->tax_code);
        $this->assertSame(1500, $item->tax_rate_bp);
        $this->assertEqualsWithDelta(4.50, (float) $item->price, 0.001);
        $this->assertEqualsWithDelta(3.91, (float) $item->net_price, 0.001);
        $this->assertEqualsWithDelta(0.59, (float) $item->tax_amount, 0.001);

        $this->assertEqualsWithDelta(9.00, (float) $order->total_amount, 0.001);
        $this->assertEqualsWithDelta(7.82, (float) $order->subtotal, 0.001);
        $this->assertEqualsWithDelta(1.18, (float) $order->tax_total, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $order->service_charge, 0.001);
        $this->assertEqualsWithDelta(9.00, (float) $order->grand_total, 0.001);
    }

    public function test_exclusive_menus_add_iva_so_the_guest_owes_the_gross(): void
    {
        $venue = $this->venue(['prices_include_tax' => false]);
        [$table, $session] = $this->openTable($venue);
        $plate = Product::create(['name' => 'Seco de chivo', 'price' => 10.00, 'editor_id' => $venue->id, 'is_available' => true]);

        $order = app(CreateOrder::class)->handle($table, $session, [$plate->id => 1]);

        $this->assertEqualsWithDelta(11.50, (float) $order->items->first()->price, 0.001);
        $this->assertEqualsWithDelta(10.00, (float) $order->subtotal, 0.001);
        $this->assertEqualsWithDelta(1.50, (float) $order->tax_total, 0.001);
        $this->assertEqualsWithDelta(11.50, (float) $order->total_amount, 0.001, 'Every existing sum-of-price path still yields what the guest owes.');
    }

    public function test_product_tax_code_overrides_the_venue_default(): void
    {
        $venue = $this->venue();
        [$table, $session] = $this->openTable($venue);
        $water = Product::create(['name' => 'Agua', 'price' => 1.00, 'tax_code' => '0', 'editor_id' => $venue->id, 'is_available' => true]);

        $order = app(CreateOrder::class)->handle($table, $session, [$water->id => 1]);

        $this->assertSame('0', $order->items->first()->tax_code);
        $this->assertEqualsWithDelta(0.0, (float) $order->tax_total, 0.001);
        $this->assertEqualsWithDelta(1.00, (float) $order->subtotal, 0.001);
    }

    public function test_a_dated_override_remaps_the_rate_for_its_window_only(): void
    {
        $venue = $this->venue();
        [$table, $session] = $this->openTable($venue);
        $beer = Product::create(['name' => 'Pilsener', 'price' => 4.60, 'editor_id' => $venue->id, 'is_available' => true]);

        // Government declares 8% tourism IVA for this weekend (platform-wide).
        TaxRateOverride::create(['from_code' => '4', 'to_code' => '8', 'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(), 'reason' => 'feriado turístico']);

        $order = app(CreateOrder::class)->handle($table, $session, [$beer->id => 1]);
        $this->assertSame('8', $order->items->first()->tax_code);
        $this->assertSame(800, $order->items->first()->tax_rate_bp);

        $this->travel(2)->days();
        $later = app(CreateOrder::class)->handle($table, $session, [$beer->id => 1]);
        $this->assertSame('4', $later->items->first()->tax_code, 'Outside the window the normal rate returns.');
    }

    public function test_service_charge_is_ten_percent_of_the_net_subtotal_and_outside_the_iva_base(): void
    {
        $venue = $this->venue(['service_charge_enabled' => true, 'service_charge_rate_bp' => 1000]);
        [$table, $session] = $this->openTable($venue);
        $plate = Product::create(['name' => 'Tabla mixta', 'price' => 115.00, 'editor_id' => $venue->id, 'is_available' => true]);

        $order = app(CreateOrder::class)->handle($table, $session, [$plate->id => 1]);

        // 115 gross = 100 net + 15 IVA. Servicio = 10% of 100, not of 115.
        $this->assertEqualsWithDelta(100.00, (float) $order->subtotal, 0.001);
        $this->assertEqualsWithDelta(15.00, (float) $order->tax_total, 0.001);
        $this->assertEqualsWithDelta(10.00, (float) $order->service_charge, 0.001);
        $this->assertEqualsWithDelta(125.00, (float) $order->grand_total, 0.001);

        $bill = TableBill::build($table);
        $this->assertEqualsWithDelta(10.00, $bill['service_charge'], 0.001);
        $this->assertEqualsWithDelta(125.00, $bill['grand_total'], 0.001);
        $this->assertSame('4', $bill['taxes'][0]['code']);
        $this->assertEqualsWithDelta(15.00, $bill['taxes'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(125.00, $bill['grand_left'], 0.001, 'Nothing paid yet: the whole bill including servicio is due.');
    }

    public function test_bill_and_guest_page_show_the_breakdown(): void
    {
        $venue = $this->venue(['service_charge_enabled' => true]);
        [$table, $session] = $this->openTable($venue);
        $plate = Product::create(['name' => 'Tabla mixta', 'price' => 115.00, 'editor_id' => $venue->id, 'is_available' => true]);
        app(CreateOrder::class)->handle($table, $session, [$plate->id => 1]);

        $this->actingAs($venue)
            ->get("/tables/{$table->id}/bill")
            ->assertOk()
            ->assertSee('Subtotal')
            ->assertSee('IVA 15%')
            ->assertSee('100.00')
            ->assertSee('15.00')
            ->assertSee('125.00');
    }

    public function test_settings_api_exposes_and_updates_the_fiscal_defaults(): void
    {
        $venue = $this->venue();
        Sanctum::actingAs($venue, $venue->apiTokenAbilities());

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('settings.default_tax_code', '4')
            ->assertJsonPath('settings.prices_include_tax', true)
            ->assertJsonPath('settings.service_charge_enabled', false);

        $this->patchJson('/api/v1/settings', [
            'currency_symbol' => '$', 'locale' => 'es',
            'default_tax_code' => '0', 'prices_include_tax' => false,
            'service_charge_enabled' => true, 'service_charge_rate_bp' => 1000,
        ])->assertOk()
            ->assertJsonPath('settings.default_tax_code', '0')
            ->assertJsonPath('settings.prices_include_tax', false)
            ->assertJsonPath('settings.service_charge_enabled', true);

        $this->patchJson('/api/v1/settings', [
            'currency_symbol' => '$', 'locale' => 'es', 'service_charge_rate_bp' => 2500,
        ])->assertStatus(422);
    }
}
