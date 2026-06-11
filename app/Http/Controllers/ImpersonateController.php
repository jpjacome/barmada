<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ImpersonateController extends Controller
{
    public function impersonate($id)
    {
        $admin = Auth::user();
        // Route is admin-gated; enforce here too for defense in depth.
        abort_unless($admin && $admin->is_admin, 403);

        $user = User::findOrFail($id);
        if (!$user->is_editor) {
            abort(403, 'You can only impersonate editors.');
        }
        session(['impersonate_admin_id' => $admin->id]);
        Auth::login($user);
        return redirect()->route('dashboard')->with('status', 'You are now impersonating this establishment. Use "Stop impersonating" to return to your admin account.');
    }

    public function leave()
    {
        // Only valid during an active impersonation: the original admin id is
        // recorded server-side at impersonation time.
        $adminId = session('impersonate_admin_id');
        if (!$adminId) {
            abort(403);
        }
        session()->forget('impersonate_admin_id');

        $admin = User::find($adminId);
        if (!$admin || !$admin->is_admin) {
            // Stored id no longer maps to an admin: fail safe by logging out.
            Auth::logout();
            return redirect('/')->with('status', 'Impersonation ended. Please log in again.');
        }

        // Restore the original admin account.
        Auth::login($admin);
        return redirect()->route('admin.dashboard')->with('status', 'Returned to your admin account.');
    }
}
