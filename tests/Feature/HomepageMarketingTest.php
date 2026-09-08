<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Frontend-polish audit items 11-13: the homepage's primary CTA must
 * point new visitors at registration (not login), and the page must
 * carry real title/meta tags instead of the generic app title.
 */
class HomepageMarketingTest extends TestCase
{
    public function test_get_started_cta_links_to_registration(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('register'), false);
        $response->assertSee(route('login'), false);
    }

    public function test_homepage_has_real_title_and_meta_description(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('<title>Barmada - Bar Management Dashboard</title>', false);
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('property="og:title"', false);
    }
}
