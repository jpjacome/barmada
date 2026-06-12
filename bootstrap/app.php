<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureIpIsApprovedForTableSession;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsEditor;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Role and guest-flow middleware aliases (Laravel 12 registration)
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'editor' => EnsureUserIsEditor::class,
            'ip.approved' => EnsureIpIsApprovedForTableSession::class,
        ]);

        // The guest device cookie must be readable on the stateless QR
        // order routes (no EncryptCookies there), so it is excluded from
        // cookie encryption. It contains only a random identifier.
        $middleware->encryptCookies(except: ['barmada_device']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Domain rules surface as 422s with their user-facing message on
        // the API; web surfaces catch these exceptions in their own UI.
        $exceptions->render(function (\App\Exceptions\DomainActionException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
