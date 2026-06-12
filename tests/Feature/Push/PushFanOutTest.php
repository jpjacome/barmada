<?php

namespace Tests\Feature\Push;

use App\Jobs\SendVenuePush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * WHERE pushes are triggered: every order-creation path, new device
 * approvals (deduplicated) and first-tap service requests — and nowhere
 * when the driver is off.
 */
class PushFanOutTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['push.driver' => 'fcm', 'push.dispatch' => 'queue']);
        Queue::fake();
    }

    public function test_guest_qr_order_pushes_to_the_venue(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $beer = $this->makeProductFor($editor, ['price' => 2.50]);

        $this->post("/order/{$table->unique_token}", [
            'products' => [$beer->id => 2],
        ])->assertRedirect();

        Queue::assertPushed(SendVenuePush::class, function (SendVenuePush $job) use ($editor, $table) {
            return $job->editorId === $editor->id
                && $job->event === 'order.created'
                && (string) $job->data['table_number'] === (string) $table->table_number;
        });
    }

    public function test_api_manual_order_pushes(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor);

        $this->apiActingAs($editor);
        $this->postJson('/api/v1/orders', [
            'table_id' => $table->id,
            'products' => [$beer->id => 1],
        ])->assertCreated();

        Queue::assertPushed(SendVenuePush::class, fn ($job) => $job->event === 'order.created');
    }

    public function test_first_scan_on_a_closed_table_pushes_an_approval_request_once(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['username' => 'cantina'])->save();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 4]);

        $device = \Illuminate\Support\Str::random(40);

        $this->withUnencryptedCookie(\App\Support\DeviceToken::COOKIE, $device)
            ->get('/qr-entry/cantina/4')->assertOk();

        Queue::assertPushed(SendVenuePush::class, function (SendVenuePush $job) {
            return $job->event === 'approval.requested'
                && (string) $job->data['table_number'] === '4';
        });

        // Same device scanning again: dedup absorbs it, no second push.
        $this->withUnencryptedCookie(\App\Support\DeviceToken::COOKIE, $device)
            ->get('/qr-entry/cantina/4')->assertOk();
        Queue::assertPushed(SendVenuePush::class, 1);
    }

    public function test_service_request_pushes_only_on_first_tap(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);

        $this->post("/order/{$table->unique_token}/service", ['type' => 'bill'])
            ->assertRedirect();
        $this->post("/order/{$table->unique_token}/service", ['type' => 'bill'])
            ->assertRedirect();

        Queue::assertPushed(SendVenuePush::class, 1);
        Queue::assertPushed(SendVenuePush::class, function (SendVenuePush $job) {
            return $job->event === 'service.requested' && $job->data['type'] === 'bill';
        });
    }

    public function test_no_dispatch_when_the_driver_is_off(): void
    {
        config(['push.driver' => 'none']);

        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $beer = $this->makeProductFor($editor);

        $this->post("/order/{$table->unique_token}", [
            'products' => [$beer->id => 1],
        ])->assertRedirect();

        Queue::assertNothingPushed();
    }
}
