<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class StaffAndSettingsApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_editor_creates_staff_with_explicit_role_flag(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Carla',
            'email' => 'carla@bar.test',
            'password' => 'secret-pass',
        ]);

        $response->assertCreated()->assertJsonPath('staff.name', 'Carla');

        $staff = User::where('email', 'carla@bar.test')->first();
        $this->assertTrue((bool) $staff->is_staff);
        $this->assertFalse((bool) $staff->is_editor);
        $this->assertSame($editor->id, $staff->editor_id);
        $this->assertTrue(Hash::check('secret-pass', $staff->password));
    }

    public function test_staff_update_keeps_password_unless_provided(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $staff = $this->makeStaff($editor);
        $originalHash = $staff->password;

        $this->patchJson("/api/v1/staff/{$staff->id}", [
            'name' => 'Renamed',
            'email' => $staff->email,
        ])->assertOk();
        $this->assertSame($originalHash, $staff->refresh()->password);

        $this->patchJson("/api/v1/staff/{$staff->id}", [
            'name' => 'Renamed',
            'email' => $staff->email,
            'password' => 'new-password',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-password', $staff->refresh()->password));
    }

    public function test_staff_management_is_editor_only_with_no_admin_bypass(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        // Staff cannot manage staff.
        $this->apiActingAs($staff);
        $this->getJson('/api/v1/staff')->assertStatus(403);
        $this->postJson('/api/v1/staff', ['name' => 'X', 'email' => 'x@x.test', 'password' => 'password1'])
            ->assertStatus(403);

        // Admins cannot either — staff tooling is deliberately editor-scoped.
        $this->apiActingAs($this->makeAdmin());
        $this->getJson('/api/v1/staff')->assertStatus(403);
    }

    public function test_staff_lookups_are_bounded_to_the_editors_tenant(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $staffB = $this->makeStaff($editorB);

        $this->apiActingAs($editorA);

        $this->patchJson("/api/v1/staff/{$staffB->id}", [
            'name' => 'Hijacked', 'email' => 'h@h.test',
        ])->assertStatus(404);
        $this->deleteJson("/api/v1/staff/{$staffB->id}")->assertStatus(404);

        // And an editor account can never be edited through staff tooling.
        $this->patchJson("/api/v1/staff/{$editorB->id}", [
            'name' => 'Hijacked', 'email' => 'h2@h.test',
        ])->assertStatus(404);
    }

    public function test_staff_delete(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $staff = $this->makeStaff($editor);

        $this->deleteJson("/api/v1/staff/{$staff->id}")->assertOk();
        $this->assertNull(User::find($staff->id));
    }

    public function test_settings_read_and_update_for_editors(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('settings.currency_symbol', '$')
            ->assertJsonPath('settings.locale', 'es')
            ->assertJsonPath('settings.business_timezone', 'UTC')
            ->assertJsonPath('settings.day_cutoff_hour', 0);

        $this->patchJson('/api/v1/settings', [
            'currency_symbol' => '€',
            'locale' => 'en',
            'business_timezone' => 'America/Guayaquil',
            'day_cutoff_hour' => 4,
        ])->assertOk()
            ->assertJsonPath('settings.currency_symbol', '€')
            ->assertJsonPath('settings.locale', 'en')
            ->assertJsonPath('settings.business_timezone', 'America/Guayaquil')
            ->assertJsonPath('settings.day_cutoff_hour', 4);

        $editor->refresh();
        $this->assertSame('€', $editor->currency_symbol);
        $this->assertSame('America/Guayaquil', $editor->business_timezone);
    }

    public function test_settings_validation_mirrors_the_web_rules(): void
    {
        $this->apiActingAs($this->makeEditor());

        $this->patchJson('/api/v1/settings', [
            'currency_symbol' => '$', 'locale' => 'fr',
        ])->assertStatus(422)->assertJsonValidationErrors('locale');

        $this->patchJson('/api/v1/settings', [
            'currency_symbol' => '$', 'locale' => 'es', 'business_timezone' => 'Mars/Olympus',
        ])->assertStatus(422)->assertJsonValidationErrors('business_timezone');

        $this->patchJson('/api/v1/settings', [
            'currency_symbol' => '$', 'locale' => 'es', 'day_cutoff_hour' => 13,
        ])->assertStatus(422)->assertJsonValidationErrors('day_cutoff_hour');
    }

    public function test_settings_are_editor_only(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->apiActingAs($staff);
        $this->getJson('/api/v1/settings')->assertStatus(403);
        $this->patchJson('/api/v1/settings', ['currency_symbol' => '£', 'locale' => 'en'])
            ->assertStatus(403);
    }
}
