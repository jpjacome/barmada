<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MetaAndAuthApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_meta_is_public_and_describes_the_server(): void
    {
        $response = $this->getJson('/api/v1/meta');

        $response->assertOk()
            ->assertJsonPath('product', 'barmada')
            ->assertJsonPath('api.version', 1)
            ->assertJsonPath('api.auth', 'sanctum-bearer');

        $this->assertContains('board', $response->json('features'));
        $this->assertContains('item-payments', $response->json('features'));
    }

    public function test_login_issues_a_token_with_role_abilities_and_venue_settings(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['currency_symbol' => '€', 'locale' => 'es'])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $editor->email,
            'password' => 'password',
            'device_name' => 'Pixel 9 (test)',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $editor->id)
            ->assertJsonPath('user.is_editor', true)
            ->assertJsonPath('user.is_staff', false)
            ->assertJsonPath('user.venue.currency_symbol', '€')
            ->assertJsonPath('user.venue.guest_locale', 'es');

        $this->assertSame(['role:editor', 'role:staff'], $response->json('abilities'));
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $editor->id,
            'name' => 'Pixel 9 (test)',
        ]);
    }

    public function test_staff_login_reports_the_owning_editors_venue_settings(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['currency_symbol' => '£'])->save();
        $staff = $this->makeStaff($editor);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'password',
            'device_name' => 'Bar phone',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.is_staff', true)
            ->assertJsonPath('user.editor_id', $editor->id)
            ->assertJsonPath('user.venue.currency_symbol', '£');

        $this->assertSame(['role:staff'], $response->json('abilities'));
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $editor = $this->makeEditor();

        $this->postJson('/api/v1/auth/login', [
            'email' => $editor->email,
            'password' => 'wrong-password',
            'device_name' => 'Pixel',
        ])->assertStatus(422);
    }

    public function test_login_requires_a_device_name(): void
    {
        $editor = $this->makeEditor();

        $this->postJson('/api/v1/auth/login', [
            'email' => $editor->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('device_name');
    }

    public function test_auth_user_returns_the_token_holder(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.id', $editor->id);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $editor = $this->makeEditor();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $editor->email,
            'password' => 'password',
            'device_name' => 'Pixel',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // New request cycle: drop cached guards and headers so the revoked
        // token is re-resolved from scratch.
        $this->flushHeaders();
        $this->app->make('auth')->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user')
            ->assertStatus(401);
    }

    public function test_protected_endpoints_reject_unauthenticated_callers(): void
    {
        $this->getJson('/api/v1/board')->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
        $this->getJson('/api/v1/tables')->assertStatus(401);
        $this->getJson('/api/v1/products')->assertStatus(401);
        $this->getJson('/api/v1/service-requests')->assertStatus(401);
        $this->getJson('/api/v1/approval-requests')->assertStatus(401);
        $this->postJson('/api/v1/devices', ['device_uuid' => 'x'])->assertStatus(401);
    }
}
