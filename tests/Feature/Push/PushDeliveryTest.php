<?php

namespace Tests\Feature\Push;

use App\Jobs\SendVenuePush;
use App\Models\ApiDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * HOW pushes are delivered: device targeting, the direct-FCM driver
 * (OAuth assertion + HTTP v1 + stale-token cleanup) and the
 * payload-light relay driver.
 */
class PushDeliveryTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // A real (throwaway) RSA key so the driver's JWT signing runs
        // for real; only the HTTP endpoints are faked.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        $this->credentialsPath = sys_get_temp_dir().'/fcm-test-'.uniqid().'.json';
        file_put_contents($this->credentialsPath, json_encode([
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config([
            'push.driver' => 'fcm',
            'push.dispatch' => 'sync',
            'push.fcm.project_id' => 'test-project',
            'push.fcm.credentials' => $this->credentialsPath,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    private function fakeFcm(int $sendStatus = 200, array $sendBody = ['name' => 'projects/test/messages/1']): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response($sendBody, $sendStatus),
        ]);
    }

    public function test_fcm_driver_targets_only_the_tenants_token_bearing_devices(): void
    {
        $this->fakeFcm();

        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);
        $otherEditor = $this->makeEditor();

        ApiDevice::create(['user_id' => $editor->id, 'device_uuid' => 'e1', 'fcm_token' => 'tok-editor']);
        ApiDevice::create(['user_id' => $staff->id, 'device_uuid' => 's1', 'fcm_token' => 'tok-staff']);
        ApiDevice::create(['user_id' => $editor->id, 'device_uuid' => 'e2', 'fcm_token' => null]); // no token
        ApiDevice::create(['user_id' => $otherEditor->id, 'device_uuid' => 'x1', 'fcm_token' => 'tok-foreign']);

        (new SendVenuePush($editor->id, 'order.created', ['table_number' => 4, 'order_id' => 9]))
            ->handle(app(\App\Push\Contracts\PushSender::class));

        // One OAuth exchange + exactly two device sends.
        Http::assertSentCount(3);

        $sentTokens = [];
        Http::assertSent(function ($request) use (&$sentTokens) {
            if (str_contains($request->url(), 'fcm.googleapis.com')) {
                $sentTokens[] = $request['message']['token'];

                // Data payload is all-strings with the event marker; the
                // human title rides along for backgrounded display.
                $this->assertSame('order.created', $request['message']['data']['event']);
                $this->assertSame('4', $request['message']['data']['table_number']);
                $this->assertSame('New order — Table 4', $request['message']['notification']['title']);
            }

            return true;
        });

        sort($sentTokens);
        $this->assertSame(['tok-editor', 'tok-staff'], $sentTokens);
    }

    public function test_unregistered_tokens_are_cleared(): void
    {
        $this->fakeFcm(404, ['error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']]);

        $editor = $this->makeEditor();
        $device = ApiDevice::create(['user_id' => $editor->id, 'device_uuid' => 'e1', 'fcm_token' => 'tok-dead']);

        (new SendVenuePush($editor->id, 'order.created', []))
            ->handle(app(\App\Push\Contracts\PushSender::class));

        $this->assertNull($device->refresh()->fcm_token);
    }

    public function test_content_can_be_disabled_for_pure_wake_pushes(): void
    {
        config(['push.include_content' => false]);
        $this->fakeFcm();

        $editor = $this->makeEditor();
        ApiDevice::create(['user_id' => $editor->id, 'device_uuid' => 'e1', 'fcm_token' => 'tok-1']);

        (new SendVenuePush($editor->id, 'order.created', ['table_number' => 4]))
            ->handle(app(\App\Push\Contracts\PushSender::class));

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'fcm.googleapis.com')) {
                $this->assertArrayNotHasKey('notification', $request['message']);
            }

            return true;
        });
    }

    public function test_relay_driver_posts_one_payload_light_batch(): void
    {
        config([
            'push.driver' => 'relay',
            'push.relay.url' => 'https://relay.barmada.test',
            'push.relay.key' => 'relay-secret',
        ]);
        Http::fake(['relay.barmada.test/*' => Http::response(['ok' => true])]);

        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);
        ApiDevice::create(['user_id' => $editor->id, 'device_uuid' => 'e1', 'fcm_token' => 'tok-1']);
        ApiDevice::create(['user_id' => $staff->id, 'device_uuid' => 's1', 'fcm_token' => 'tok-2']);

        (new SendVenuePush($editor->id, 'order.created', ['table_number' => 4, 'order_id' => 9]))
            ->handle(app(\App\Push\Contracts\PushSender::class));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $this->assertSame('https://relay.barmada.test/v1/push', $request->url());
            $this->assertSame('Bearer relay-secret', $request->header('Authorization')[0]);
            $this->assertSame('order.created', $request['event']);
            $this->assertEqualsCanonicalizing(['tok-1', 'tok-2'], $request['tokens']);

            // Payload-light: no order data, no table numbers, no titles.
            $this->assertArrayNotHasKey('data', $request->data());
            $this->assertArrayNotHasKey('table_number', $request->data());

            return true;
        });
    }

    public function test_no_devices_means_no_http_at_all(): void
    {
        $this->fakeFcm();
        $editor = $this->makeEditor();

        (new SendVenuePush($editor->id, 'order.created', []))
            ->handle(app(\App\Push\Contracts\PushSender::class));

        Http::assertNothingSent();
    }
}
