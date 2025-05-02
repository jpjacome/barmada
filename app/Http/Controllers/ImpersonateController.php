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
        $user = User::findOrFail($id);
        if (!$user->is_editor) {
            abort(403, 'You can only impersonate editors.');
        }
        session(['impersonate_admin_id' => $admin->id]);
        Auth::login($user);
        return redirect()->route('dashboard')->with('status', 'You are now impersonating this establishment. To return to your admin account, please log out and log in again.');
    }

    public function leave()
    {
        Auth::logout();
        session()->forget('impersonate_admin_id');
        return redirect('/')->with('status', 'You have been logged out. Please log in as admin to return to your dashboard.');
    }
}
