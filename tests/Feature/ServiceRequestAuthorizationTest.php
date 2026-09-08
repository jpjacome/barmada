<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Resolving a guest's "bring the bill" request must be authorised by
 * policy, not merely hidden by the global scope.
 */
class ServiceRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(User $editor): ServiceRequest
    {
        $table = Table::create(['editor_id' => $editor->id, 'table_number' => 1, 'status' => 'open']);
        $session = TableSession::create([
            'table_id' => $table->id, 'session_number' => 1, 'date' => now()->toDateString(),
            'unique_token' => (string) Str::uuid(), 'status' => 'open', 'opened_at' => now(),
            'opened_by' => $editor->id, 'editor_id' => $editor->id,
        ]);

        return ServiceRequest::create([
            'table_id' => $table->id, 'table_session_id' => $session->id, 'editor_id' => $editor->id,
            'type' => 'bill', 'status' => 'pending',
        ]);
    }

    public function test_own_venue_can_resolve_a_request(): void
    {
        $editor = User::factory()->create(['is_editor' => true, 'username' => 'a'.uniqid()]);
        $request = $this->requestFor($editor);

        Sanctum::actingAs($editor, $editor->apiTokenAbilities());

        $this->postJson("/api/v1/service-requests/{$request->id}/done")->assertOk();
        $this->assertSame('done', $request->fresh()->status);
    }

    public function test_another_venue_cannot_resolve_it_even_when_the_scope_is_bypassed(): void
    {
        $owner = User::factory()->create(['is_editor' => true, 'username' => 'o'.uniqid()]);
        $rival = User::factory()->create(['is_editor' => true, 'username' => 'r'.uniqid()]);
        $request = $this->requestFor($owner);

        // Belt: the scope hides it (404 through the API).
        Sanctum::actingAs($rival, $rival->apiTokenAbilities());
        $this->postJson("/api/v1/service-requests/{$request->id}/done")->assertNotFound();

        // Braces: the policy denies it even if a caller reached the row.
        $this->assertFalse($rival->can('update', ServiceRequest::withoutGlobalScopes()->find($request->id)));
        $this->assertTrue($owner->can('update', ServiceRequest::withoutGlobalScopes()->find($request->id)));
        $this->assertSame('pending', $request->fresh()->status);
    }
}
