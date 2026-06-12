<?php

namespace Tests\Feature\Api;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ServiceRequestAndProductApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    private function makeServiceRequestFor($editor, $type = ServiceRequest::TYPE_WAITER): ServiceRequest
    {
        [$table, $session] = $this->openTableWithSession($editor);

        return ServiceRequest::create([
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'editor_id' => $editor->id,
            'type' => $type,
            'status' => 'pending',
        ]);
    }

    public function test_service_requests_list_is_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $requestA = $this->makeServiceRequestFor($editorA);
        $this->makeServiceRequestFor($editorB);

        $this->apiActingAs($editorA);

        $rows = $this->getJson('/api/v1/service-requests')->json('service_requests');

        $this->assertCount(1, $rows);
        $this->assertSame($requestA->id, $rows[0]['id']);
    }

    public function test_done_resolves_and_is_idempotent(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);
        $request = $this->makeServiceRequestFor($editor);

        $this->apiActingAs($staff);

        $this->postJson("/api/v1/service-requests/{$request->id}/done")
            ->assertOk()
            ->assertJsonPath('service_request.status', 'done');

        $request->refresh();
        $this->assertSame($staff->id, $request->resolved_by);
        $resolvedAt = $request->resolved_at;

        // Second tap: still done, resolution metadata untouched.
        $this->postJson("/api/v1/service-requests/{$request->id}/done")->assertOk();
        $this->assertEquals($resolvedAt, $request->refresh()->resolved_at);
    }

    public function test_foreign_tenant_service_request_404s(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $requestB = $this->makeServiceRequestFor($editorB);

        $this->apiActingAs($editorA);

        $this->postJson("/api/v1/service-requests/{$requestB->id}/done")->assertStatus(404);
    }

    public function test_products_list_is_tenant_bounded_with_availability(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $this->makeProductFor($editorA, ['name' => 'Pilsener', 'is_available' => true]);
        $this->makeProductFor($editorA, ['name' => 'Nachos', 'is_available' => false]);
        $this->makeProductFor($editorB, ['name' => 'Mojito']);

        $this->apiActingAs($editorA);

        $all = $this->getJson('/api/v1/products')->json('products');
        $this->assertCount(2, $all);

        $available = $this->getJson('/api/v1/products?available=1')->json('products');
        $this->assertCount(1, $available);
        $this->assertSame('Pilsener', $available[0]['name']);
    }

    public function test_staff_can_86_a_product(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);
        $product = $this->makeProductFor($editor, ['is_available' => true]);

        $this->apiActingAs($staff);

        $this->postJson("/api/v1/products/{$product->id}/toggle-availability")
            ->assertOk()
            ->assertJsonPath('product.is_available', false);

        $this->assertFalse((bool) $product->refresh()->is_available);
    }
}
