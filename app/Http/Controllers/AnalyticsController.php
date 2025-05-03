<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!($user && ($user->is_admin || $user->is_editor))) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this page.');
        }
        // You can add analytics logic here later
        return view('analytics');
    }
}
