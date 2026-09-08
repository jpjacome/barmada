<?php

namespace Tests\Feature\Fiscal;

use App\Actions\Fiscal\IssueFactura;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\ToggleItemPaid;
use App\Actions\Tables\SaveClientInvoice;
use App\Exceptions\DomainActionException;
use App\Fiscal\ClaveAcceso;
use App\Models\FiscalDocument;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IssueFacturaTest extends TestCase
{
    use RefreshDatabase;

    private function venue(array $overrides = []): User
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid(), 'business_timezone' => 'America/Guayaquil']);
        $venue->forceFill(array_merge([
            'fiscal_enabled' => true,
            'fiscal_ruc' => '1790012345001',
            'fiscal_razon_social' => 'LA CANTINA S.A.S.',
            'fiscal_nombre_comercial' => 'La Cantina',
            'fiscal_dir_matriz' => 'Av. Amazonas N24-03 y Colón, Quito',
            'fiscal_estab' => '001',
            'fiscal_pto_emi' => '001',
            'fiscal_ambiente' => 1,
            'fiscal_provider' => 'none',
            'service_charge_enabled' => true,
        ], $overrides))->save();

        return $venue->refresh();
    }

    private function tableWithOrder(User $venue, float $price, int $units = 1): Table
    {
        $table = Table::create(['editor_id' => $venue->id, 'table_number' => 5, 'status' => 'open']);
        $session = TableSession::create([
            'table_id' => $table->id, 'session_number' => 1, 'date' => now()->toDateString(),
            'unique_token' => (string) Str::uuid(), 'status' => 'open', 'opened_at' => now(),
            'opened_by' => $venue->id, 'editor_id' => $venue->id,
        ]);
        $product = Product::create(['name' => 'Tabla mixta & más', 'price' => $price, 'editor_id' => $venue->id, 'is_available' => true]);
        app(CreateOrder::class)->handle($table, $session, [$product->id => $units]);

        return $table;
    }

    public function test_issues_a_numbered_factura_with_matching_xml_for_a_small_cash_bill(): void
    {
        $venue = $this->venue();
        $table = $this->tableWithOrder($venue, 11.50, 2); // 23.00 gross = 20.00 net + 3.00 IVA; servicio 2.00 → 25.00

        $doc = app(IssueFactura::class)->handle($table, $venue);

        $this->assertSame('001-001-000000001', $doc->number());
        $this->assertSame(FiscalDocument::STATUS_BUILT, $doc->status, 'No transport configured: parked, unsigned.');
        $this->assertTrue(ClaveAcceso::isValid($doc->clave_acceso));
        $this->assertSame('07', $doc->buyer_id_type);
        $this->assertSame('9999999999999', $doc->buyer_identification);
        $this->assertEqualsWithDelta(20.00, (float) $doc->subtotal, 0.001);
        $this->assertEqualsWithDelta(3.00, (float) $doc->tax_total, 0.001);
        $this->assertEqualsWithDelta(2.00, (float) $doc->propina, 0.001);
        $this->assertEqualsWithDelta(25.00, (float) $doc->importe_total, 0.001);

        $xml = simplexml_load_string($doc->xml_unsigned);
        $this->assertSame('1.1.0', (string) $xml['version']);
        $this->assertSame('comprobante', (string) $xml['id']);
        $this->assertSame('1790012345001', (string) $xml->infoTributaria->ruc);
        $this->assertSame('000000001', (string) $xml->infoTributaria->secuencial);
        $this->assertSame($doc->clave_acceso, (string) $xml->infoTributaria->claveAcceso);
        $this->assertSame('20.00', (string) $xml->infoFactura->totalSinImpuestos);
        $this->assertSame('2.00', (string) $xml->infoFactura->propina);
        $this->assertSame('25.00', (string) $xml->infoFactura->importeTotal);
        $this->assertSame('DOLAR', (string) $xml->infoFactura->moneda);
        $this->assertSame('4', (string) $xml->infoFactura->totalConImpuestos->totalImpuesto->codigoPorcentaje);
        $this->assertSame('3.00', (string) $xml->infoFactura->totalConImpuestos->totalImpuesto->valor);
        $this->assertSame('01', (string) $xml->infoFactura->pagos->pago->formaPago, 'Unpaid/unspecified = cash');
        $this->assertSame('25.00', (string) $xml->infoFactura->pagos->pago->total, 'Pagos sum to importeTotal, propina included.');

        $detalle = $xml->detalles->detalle;
        $this->assertSame('Tabla mixta & más', (string) $detalle->descripcion, 'Ampersand round-trips through the serializer.');
        $this->assertSame('2.000000', (string) $detalle->cantidad);
        $this->assertSame('10.000000', (string) $detalle->precioUnitario);
        $this->assertSame('20.00', (string) $detalle->precioTotalSinImpuesto);
        $this->assertSame('15.00', (string) $detalle->impuestos->impuesto->tarifa);
        $this->assertStringContainsString('&amp;', $doc->xml_unsigned);
        $this->assertStringNotContainsString("\n", (string) $detalle->descripcion);
    }

    public function test_sequence_advances_and_a_session_is_invoiced_once(): void
    {
        $venue = $this->venue();
        $first = $this->tableWithOrder($venue, 5.00);
        $doc1 = app(IssueFactura::class)->handle($first, $venue);
        $this->assertSame(1, $doc1->secuencial);

        $this->expectException(DomainActionException::class);
        app(IssueFactura::class)->handle($first, $venue);
    }

    public function test_bills_above_fifty_need_an_identified_buyer(): void
    {
        $venue = $this->venue(['service_charge_enabled' => false]);
        $table = $this->tableWithOrder($venue, 60.00);

        try {
            app(IssueFactura::class)->handle($table, $venue);
            $this->fail('Expected the consumidor-final threshold to block.');
        } catch (DomainActionException $e) {
            $this->assertStringContainsString('50.00', $e->getMessage());
        }

        app(SaveClientInvoice::class)->handle($table, ['name' => 'Ana Pérez', 'tax_id' => '1712345678']);
        $doc = app(IssueFactura::class)->handle($table, $venue);

        $this->assertSame('05', $doc->buyer_id_type, 'A 10-digit id is a cédula.');
        $this->assertSame('1712345678', $doc->buyer_identification);
        $this->assertSame('Ana Pérez', $doc->buyer_name);
    }

    public function test_payments_follow_the_recorded_methods(): void
    {
        $venue = $this->venue(['service_charge_enabled' => false]);
        $table = $this->tableWithOrder($venue, 10.00, 2);
        $order = $table->currentSessionOrders()->first();
        $productId = $order->items->first()->product_id;
        app(ToggleItemPaid::class)->handle($order, $productId, 0, $venue, 'card');

        $doc = app(IssueFactura::class)->handle($table, $venue);

        $byCode = collect($doc->payments)->keyBy('forma_pago');
        $this->assertEqualsWithDelta(10.00, $byCode['19']['total'], 0.001, 'card → tarjeta de crédito');
        $this->assertEqualsWithDelta(10.00, $byCode['01']['total'], 0.001, 'the unpaid unit is cash');
    }

    public function test_incomplete_profile_or_disabled_invoicing_is_refused(): void
    {
        $venue = $this->venue(['fiscal_ruc' => null]);
        $table = $this->tableWithOrder($venue, 5.00);

        $this->expectException(DomainActionException::class);
        app(IssueFactura::class)->handle($table, $venue);
    }

    public function test_api_issues_and_reads_documents_within_the_tenant(): void
    {
        $venue = $this->venue(['service_charge_enabled' => false]);
        $table = $this->tableWithOrder($venue, 4.50);
        Sanctum::actingAs($venue, $venue->apiTokenAbilities());

        $created = $this->postJson("/api/v1/tables/{$table->id}/factura")
            ->assertCreated()
            ->assertJsonPath('document.number', '001-001-000000001')
            ->assertJsonPath('document.status', 'built');

        $id = $created->json('document.id');
        $this->getJson("/api/v1/fiscal-documents/{$id}")->assertOk()->assertJsonPath('document.totals.importe_total', 4.5);
        $this->getJson('/api/v1/fiscal-documents')->assertOk()->assertJsonCount(1, 'documents');

        $rival = User::factory()->create(['is_editor' => true, 'username' => 'rival'.uniqid()]);
        Sanctum::actingAs($rival, $rival->apiTokenAbilities());
        $this->getJson("/api/v1/fiscal-documents/{$id}")->assertNotFound();
    }

    public function test_web_flow_issues_and_renders_the_ride(): void
    {
        $venue = $this->venue();
        $table = $this->tableWithOrder($venue, 11.50, 2);

        $this->actingAs($venue)
            ->post("/tables/{$table->id}/factura")
            ->assertRedirect();

        $doc = FiscalDocument::withoutGlobalScopes()->first();

        $this->actingAs($venue)
            ->get("/fiscal/{$doc->id}/ride")
            ->assertOk()
            ->assertSee('001-001-000000001')
            ->assertSee($doc->clave_acceso)
            ->assertSee('CONSUMIDOR FINAL')
            ->assertSee('25.00')
            ->assertSee('PENDIENTE DE AUTORIZACIÓN');

        $this->actingAs($venue)->get("/fiscal/{$doc->id}/xml")->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        // The bill now points at the factura instead of offering to issue.
        $this->actingAs($venue)->get("/tables/{$table->id}/bill")->assertSee('Ver RIDE');
    }
}
