<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated and is an admin
        if (!$request->user() || !$request->user()->is_admin) {
            // Redirect to dashboard with an error message
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
} 