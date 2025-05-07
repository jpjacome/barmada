<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:20', 'unique:users,username', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:50', 'unique:'.User::class],
            'business_name' => ['required', 'string', 'max:100'],
            'table_count' => ['required', 'integer', 'min:1', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'username' => $request->username,
            'business_name' => $request->business_name,
            'name' => $request->business_name, // Use business_name for required name field
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_editor' => true, // Ensure every new user is an editor
        ]);
        $user->editor_id = $user->id;
        $user->save();

        // Create tables for the new editor
        $tableCount = min((int)$request->input('table_count'), 50);
        for ($i = 1; $i <= $tableCount; $i++) {
            \App\Models\Table::create([
                'editor_id' => $user->id,
                'table_number' => $i,
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
