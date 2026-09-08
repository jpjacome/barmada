<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The guest-facing pages render inside their own layout (no operator
 * navigation) and consistently pick a locale even when there is no table
 * in scope — the waiting-approval page reached directly, with no QR
 * context, previously fell back to the framework's English default no
 * matter the visitor's browser language.
 */
class GuestLocaleTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_guest_order_page_does_not_render_operator_navigation(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);

        $response = $this->get('/order/'.$table->unique_token)->assertOk();

        $response->assertDontSee('nav-new-order-button', false);
        $response->assertDontSee('Bar Management Dashboard');
    }

    public function test_waiting_approval_with_no_table_context_defaults_to_spanish(): void
    {
        $response = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get('/orders/waiting-approval')
            ->assertOk();

        $response->assertSee('Solicitud de Mesa Pendiente');
    }

    public function test_waiting_approval_with_no_table_context_honours_english_browser_language(): void
    {
        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/orders/waiting-approval')
            ->assertOk();

        $response->assertSee('Table Request Pending');
    }

    public function test_waiting_approval_with_no_table_context_honours_spanish_browser_language(): void
    {
        $response = $this->withHeader('Accept-Language', 'es-EC,es;q=0.9')
            ->get('/orders/waiting-approval')
            ->assertOk();

        $response->assertSee('Solicitud de Mesa Pendiente');
    }
}
