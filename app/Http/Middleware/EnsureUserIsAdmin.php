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
        $user = $request->user();
        // Allow if user is admin, regardless of is_editor
        if (!$user || !$user->is_admin) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this page.');
        }
        return $next($request);
    }
}