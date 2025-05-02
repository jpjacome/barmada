<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Set theme based on user preferences if available
        $user = Auth::user();
        if ($user && isset($user->preferences['theme'])) {
            session(['theme' => $user->preferences['theme']]);
        }

        // Redirect based on user role
        if ($user && $user->is_admin) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        } elseif ($user && $user->is_editor) {
            return redirect()->intended(route('dashboard', absolute: false));
        }
        // Default fallback
        return redirect('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
