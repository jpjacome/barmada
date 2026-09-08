<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The operator dashboard (editors and their staff) renders in the venue's
 * configured locale, set via SetLocale::handle() reading
 * User::venueSettingsUser()->locale — independent of the guest-facing
 * locale negotiation covered by GuestLocaleTest. [F-9]
 */
class OperatorLocaleTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_editor_with_spanish_locale_sees_spanish_dashboard_heading(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['locale' => 'es'])->save();

        $response = $this->actingAs($editor)->get('/dashboard')->assertOk();

        // The greeting prefers first name over username (dashboard.blade.php).
        $response->assertSee('Hola, '.$editor->first_name);
    }

    public function test_editor_with_english_locale_sees_english_dashboard_heading(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['locale' => 'en'])->save();

        $response = $this->actingAs($editor)->get('/dashboard')->assertOk();

        $response->assertSee('Hello, '.$editor->first_name);
    }

    public function test_staff_inherits_their_editors_locale(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['locale' => 'en'])->save();
        $staff = $this->makeStaff($editor);

        $response = $this->actingAs($staff)->get('/dashboard')->assertOk();

        $response->assertSee('Hello, '.$staff->first_name);
    }
}
