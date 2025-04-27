<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*--------------------------------------------------------------
        | 1.  Force every generated URL to live under /barmada/public
        --------------------------------------------------------------*/
        URL::forceRootUrl(config('app.url'));               // APP_URL in .env
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        /*--------------------------------------------------------------
        | 2.  Custom directive that prints the correct Livewire script
        --------------------------------------------------------------*/
        if (class_exists(Livewire::class)) {
            Blade::directive('fixedLivewireScripts', function () {
                $src    = asset('vendor/livewire/livewire.js');
                $csrf   = csrf_token();
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
    }
}
