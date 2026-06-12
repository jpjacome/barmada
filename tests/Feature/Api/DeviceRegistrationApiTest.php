<?php

namespace Tests\Feature\Api;

use App\Models\ApiDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeviceRegistrationApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_device_registers_and_refreshes_in_place(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());

        $this->postJson('/api/v1/devices', [
            'device_uuid' => 'pixel-9-test',
            'platform' => 'android',
            'name' => 'Bar phone',
        ])->assertCreated()->assertJsonPath('device.has_push_token', false);

        // Re-registration with an FCM token updates the same row.
        $this->postJson('/api/v1/devices', [
            'device_uuid' => 'pixel-9-test',
            'platform' => 'android',
            'fcm_token' => 'fcm-abc-123',
        ])->assertOk()->assertJsonPath('device.has_push_token', true);

        $this->assertSame(1, ApiDevice::count());
        $this->assertSame('fcm-abc-123', ApiDevice::first()->fcm_token);
        $this->assertSame($editor->id, ApiDevice::first()->user_id);
    }

    public function test_platform_is_validated(): void
    {
        $this->apiActingAs($this->makeEditor());

        $this->postJson('/api/v1/devices', [
            'device_uuid' => 'x',
            'platform' => 'windows-phone',
        ])->assertStatus(422)->assertJsonValidationErrors('platform');
    }

    public function test_unregistering_removes_only_the_callers_device(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        ApiDevice::create(['user_id' => $editorA->id, 'device_uuid' => 'shared-uuid']);
        ApiDevice::create(['user_id' => $editorB->id, 'device_uuid' => 'shared-uuid']);

        $this->apiActingAs($editorA);

        $this->deleteJson('/api/v1/devices/shared-uuid')->assertOk();

        $this->assertSame(0, ApiDevice::where('user_id', $editorA->id)->count());
        $this->assertSame(1, ApiDevice::where('user_id', $editorB->id)->count());
    }
}
