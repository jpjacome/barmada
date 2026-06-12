<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsEditor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Push delivery driver (proposal §5.3): direct FCM with the
        // venue's own service account, the hosted payload-light relay,
        // or nothing at all (the default — the app then relies on
        // foreground polling).
        $this->app->bind(\App\Push\Contracts\PushSender::class, function () {
            return match (config('push.driver', 'none')) {
                'fcm' => new \App\Push\FcmDirectSender(
                    config('push.fcm.project_id'),
                    config('push.fcm.credentials'),
                    (bool) config('push.include_content', true),
                ),
                'relay' => new \App\Push\RelaySender(
                    config('push.relay.url'),
                    config('push.relay.key'),
                ),
                default => new \App\Push\NullSender,
            };
        });
    }

    public function boot(): void
    {
        /*--------------------------------------------------------------
        | 1.  Force every generated URL to live under /barmada/public
        --------------------------------------------------------------*/
        // Only force the root URL when APP_URL is actually configured; forcing
        // an empty root URL would corrupt every generated link.
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);               // APP_URL in .env
        }
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        /*--------------------------------------------------------------
        | 2.  Custom directive that prints the correct Livewire script
        --------------------------------------------------------------*/
        if (class_exists(Livewire::class)) {
            Blade::directive('fixedLivewireScripts', function () {
                $src = asset('vendor/livewire/livewire.js');
                $csrf = csrf_token();
                $update = url('livewire/update');            // honours forceRootUrl

                return <<<HTML
<script src="{$src}"
        data-csrf="{$csrf}"
        data-update-uri="{$update}"
        data-navigate-once="true"></script>
HTML;
            });
        }

        /*--------------------------------------------------------------
        | 3.  SVG upload helpers (unchanged)
        --------------------------------------------------------------*/
        \Illuminate\Http\UploadedFile::macro('isSvg', function () {
            $mimeType = $this->getMimeType();

            return $mimeType === 'image/svg+xml'
                || $mimeType === 'text/plain'
                || $mimeType === 'application/xml'
                || $mimeType === 'text/xml'
                || $mimeType === 'application/octet-stream';
        });

        \Illuminate\Support\Facades\Validator::extend('svg', function ($attribute, $value) {
            return $value instanceof \Illuminate\Http\UploadedFile && $value->isSvg();
        });

        // Register 'editor' middleware alias for Laravel 12+
        Route::aliasMiddleware('editor', EnsureUserIsEditor::class);
    }
}
