<?php

namespace Tests\Feature\Auth;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // Registration creates an editor account with its own tenant and
        // provisions its tables.
        $response = $this->post('/register', [
            'username' => 'testbar',
            'email' => 'test@example.com',
            'business_name' => 'Test Bar',
            'table_count' => 3,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue((bool) $user->is_editor);
        $this->assertFalse((bool) $user->is_admin);
        $this->assertSame($user->id, $user->editor_id);
        $this->assertSame(3, Table::acrossEditors()->where('editor_id', $user->id)->count());
    }
}
