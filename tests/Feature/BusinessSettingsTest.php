<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Per-venue currency + guest language settings. [F-9, F-10]
 */
class BusinessSettingsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_editor_can_update_business_settings(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->post('/settings/business', ['currency_symbol' => '€', 'locale' => 'en'])
            ->assertRedirect(route('settings.index', absolute: false));

        $editor->refresh();
        $this->assertSame('€', $editor->currencySymbol());
        $this->assertSame('en', $editor->guestLocale());
    }

    public function test_defaults_preserve_previous_behaviour(): void
    {
        $editor = $this->makeEditor();

        $this->assertSame('$', $editor->currencySymbol());
        $this->assertSame('es', $editor->guestLocale());
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->from('/settings')
            ->post('/settings/business', ['currency_symbol' => '$', 'locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_staff_and_guests_cannot_change_business_settings(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->actingAs($staff)
            ->post('/settings/business', ['currency_symbol' => '€', 'locale' => 'en'])
            ->assertForbidden();

        $this->post('/logout');

        $this->post('/settings/business', ['currency_symbol' => '€', 'locale' => 'en'])
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_settings_page_shows_business_settings_to_editors(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Business Settings')
            ->assertSee('Guest menu language');
    }
}
