<?php

namespace App\Push\Contracts;

use App\Push\PushMessage;
use Illuminate\Support\Collection;

interface PushSender
{
    /**
     * Deliver one message to a set of registered devices. Implementations
     * must never throw on per-device failures — log, clean up stale
     * tokens, and move on; pushes are best-effort by design (the app's
     * foreground polling remains the source of truth).
     *
     * @param  Collection<int, \App\Models\ApiDevice>  $devices
     */
    public function send(PushMessage $message, Collection $devices): void;
}
