<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * GET /api/v1/tables/scan — staff QR resolution.
 *
 * The endpoint takes the username and table_number from a guest QR URL
 * (`/qr-entry/{username}/{table_number}`) and returns the table id so
 * the app can navigate straight to the session screen.
 */
class QrScanApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_editor_can_resolve_own_table_qr(): void
    {
        $editor = $this->makeEditor();
        $table  = $this->makeTableFor($editor, ['table_number' => 5, 'status' => 'open']);

        $this->apiActingAs($editor);

        $this->getJson("/api/v1/tables/scan?username={$editor->username}&table_number=5")
            ->assertOk()
            ->assertJsonPath('table.id', $table->id)
            ->assertJsonPath('table.table_number', 5)
            ->assertJsonPath('table.status', 'open');
    }

    public function test_staff_can_resolve_own_venue_table_qr(): void
    {
        $editor = $this->makeEditor();
        $staff  = $this->makeStaff($editor);
        $table  = $this->makeTableFor($editor, ['table_number' => 3, 'status' => 'closed']);

        $this->apiActingAs($staff);

        $this->getJson("/api/v1/tables/scan?username={$editor->username}&table_number=3")
            ->assertOk()
            ->assertJsonPath('table.id', $table->id);
    }

    public function test_cross_venue_scan_is_forbidden(): void
    {
        $editor1 = $this->makeEditor();
        $editor2 = $this->makeEditor();
        $this->makeTableFor($editor2, ['table_number' => 1]);

        // editor1's token may not resolve editor2's QR code.
        $this->apiActingAs($editor1);

        $this->getJson("/api/v1/tables/scan?username={$editor2->username}&table_number=1")
            ->assertStatus(403);
    }

    public function test_unknown_venue_returns_404(): void
    {
        $editor = $this->makeEditor();
        $this->apiActingAs($editor);

        $this->getJson('/api/v1/tables/scan?username=does_not_exist_ever&table_number=1')
            ->assertStatus(404);
    }

    public function test_unknown_table_number_returns_404(): void
    {
        $editor = $this->makeEditor();
        $this->makeTableFor($editor, ['table_number' => 7]);
        $this->apiActingAs($editor);

        $this->getJson("/api/v1/tables/scan?username={$editor->username}&table_number=99")
            ->assertStatus(404);
    }

    public function test_archived_table_is_not_resolvable(): void
    {
        $editor = $this->makeEditor();
        $this->makeTableFor($editor, ['table_number' => 2, 'archived_at' => now()]);
        $this->apiActingAs($editor);

        $this->getJson("/api/v1/tables/scan?username={$editor->username}&table_number=2")
            ->assertStatus(404);
    }

    public function test_unauthenticated_scan_is_rejected(): void
    {
        $this->getJson('/api/v1/tables/scan?username=any&table_number=1')
            ->assertUnauthorized();
    }
}
