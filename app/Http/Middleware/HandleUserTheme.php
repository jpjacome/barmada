<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleUserTheme
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If user is authenticated and has a theme preference
        if (auth()->check()) {
            $user = auth()->user();
            $preferences = $user->preferences ?? [];
            
            // If user has a theme preference, set it in the session
            if (isset($preferences['theme'])) {
                session(['theme' => $preferences['theme']]);
            }
        }

        return $next($request);
    }
}