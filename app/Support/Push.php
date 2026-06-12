<?php

namespace App\Support;

use App\Jobs\SendVenuePush;

/**
 * The one-line entry point the domain code calls when something
 * push-worthy happens at a venue. Honors the configured driver and
 * dispatch mode; a "none" driver costs a single config read.
 */
class Push
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function venue(int|string|null $editorId, string $event, array $data = []): void
    {
        if ($editorId === null || config('push.driver', 'none') === 'none') {
            return;
        }

        $job = new SendVenuePush((int) $editorId, $event, $data);

        if (config('push.dispatch', 'sync') === 'queue') {
            dispatch($job);
        } else {
            dispatch_sync($job);
        }
    }
}
