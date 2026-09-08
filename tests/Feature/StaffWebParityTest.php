<?php

namespace Tests\Feature;

use App\Livewire\ProductsList;
use App\Livewire\TablesList;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Staff belong to a venue and must see that venue on the web exactly as
 * the API already shows it to them. The web components used to re-derive
 * tenancy by hand, and the products page had no staff branch at all:
 * a waiter saw "No products found" while /api/v1/products served them the
 * catalog.
 */
class StaffWebParityTest extends TestCase
{
    use RefreshDatabase;

    private function venueWithStaff(): array
    {
        $editor = User::factory()->create([
            'is_editor' => true,
            'username' => 'bar'.uniqid(),
            'business_name' => 'La Cantina',
            'business_timezone' => 'America/Guayaquil',
        ]);

        $staff = User::factory()->create([
            'username' => 'mesero'.uniqid(),
            'first_name' => 'Marta',
        ]);
        $staff->forceFill(['is_staff' => true, 'editor_id' => $editor->id])->save();

        $rival = User::factory()->create(['is_editor' => true, 'username' => 'rival'.uniqid()]);

        Product::create(['name' => 'Pilsener', 'price' => 2.5, 'editor_id' => $editor->id, 'is_available' => true]);
        Product::create(['name' => 'Rival Beer', 'price' => 9.9, 'editor_id' => $rival->id, 'is_available' => true]);
        Table::create(['editor_id' => $editor->id, 'table_number' => 1, 'status' => 'closed']);
        Table::create(['editor_id' => $rival->id, 'table_number' => 1, 'status' => 'closed']);

        return [$editor, $staff];
    }

    public function test_staff_see_their_venues_products_on_the_web(): void
    {
        [, $staff] = $this->venueWithStaff();

        $this->actingAs($staff);

        Livewire::test(ProductsList::class)
            ->assertSee('Pilsener')
            ->assertDontSee('Rival Beer');
    }

    public function test_staff_see_their_venues_tables_and_nobody_elses(): void
    {
        [, $staff] = $this->venueWithStaff();

        $this->actingAs($staff);

        $component = Livewire::test(TablesList::class);
        $this->assertCount(1, $component->instance()->tables);

        $this->get('/tables')->assertOk();
    }

    public function test_staff_dashboard_shows_the_venue_and_hides_owner_only_links(): void
    {
        [$editor, $staff] = $this->venueWithStaff();

        $this->actingAs($staff);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Hello, Marta')
            ->assertSee('La Cantina')
            ->assertDontSee(route('orders.archive'));

        $this->actingAs($editor);

        $this->get('/dashboard')->assertSee(route('orders.archive'));
    }

    public function test_dashboard_activity_names_the_table_number_not_the_row_id(): void
    {
        [$editor] = $this->venueWithStaff();
        $table = Table::withoutGlobalScopes()->where('editor_id', $editor->id)->first();
        // Force the row id and the venue's table number apart.
        $table->forceFill(['table_number' => 42])->save();

        \App\Models\Order::create([
            'table_id' => $table->id,
            'status' => 'pending',
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_left' => 0,
            'editor_id' => $editor->id,
        ]);

        $this->actingAs($editor);

        $this->get('/dashboard')->assertSee('for Table 42');
    }
}
