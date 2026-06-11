<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The guest menu must show every product of the venue — including
 * uncategorized ones [F-2] — in the venue's own language and currency
 * [F-9, F-10], with the running-total cart bar [M-3a].
 */
class MenuRenderingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_uncategorized_products_are_visible_on_the_guest_menu(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);

        $category = $this->makeCategoryFor($editor, ['name' => 'Drinks', 'sort_order' => 1]);
        $this->makeProductFor($editor, ['name' => 'Craft Beer', 'category_id' => $category->id]);
        $this->makeProductFor($editor, ['name' => 'Mystery Snack', 'category_id' => null]);

        $response = $this->get('/order/'.$table->unique_token)->assertOk();

        $response->assertSee('Craft Beer');
        // Previously invisible: products without a category never rendered.
        $response->assertSee('Mystery Snack');
    }

    public function test_menu_uses_the_venues_language_and_currency(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['locale' => 'en', 'currency_symbol' => '€'])->save();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $this->makeProductFor($editor, ['name' => 'Craft Beer', 'price' => 4.50]);

        $response = $this->get('/order/'.$table->unique_token)->assertOk();

        $response->assertSee('Place Order');
        $response->assertSee('€4.50');
        $response->assertDontSee('Realizar Pedido');
    }

    public function test_menu_defaults_to_spanish_and_dollar(): void
    {
        // Defaults mirror the app's previous hard-coded behaviour, so
        // existing venues see no change after the migration.
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $this->makeProductFor($editor, ['name' => 'Craft Beer', 'price' => 4.50]);

        $response = $this->get('/order/'.$table->unique_token)->assertOk();

        $response->assertSee('Realizar Pedido');
        $response->assertSee('$4.50');
    }

    public function test_menu_includes_the_cart_total_bar(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $this->approveDevice($session);
        $this->makeProductFor($editor, ['name' => 'Craft Beer', 'price' => 4.50]);

        $response = $this->get('/order/'.$table->unique_token)->assertOk();

        $response->assertSee('cart-bar', false);
        $response->assertSee('order-review-modal', false);
        $response->assertSee('data-price="4.5', false);
    }
}
