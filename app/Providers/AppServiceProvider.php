<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register SVG MIME type mapping
        \Illuminate\Http\UploadedFile::macro('isSvg', function () {
            $mimeType = $this->getMimeType();
            return $mimeType === 'image/svg+xml' 
                || $mimeType === 'text/plain' 
                || $mimeType === 'application/xml'
                || $mimeType === 'text/xml'
                || $mimeType === 'application/octet-stream';
        });
        
        // Register custom validation rule for SVG files
        \Illuminate\Support\Facades\Validator::extend('svg', function ($attribute, $value, $parameters, $validator) {
            if (!$value instanceof \Illuminate\Http\UploadedFile) {
                return false;
            }
            
            return $value->isSvg();
        });
    }
}
