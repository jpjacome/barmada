<?php

namespace App\Push;

use App\Push\Contracts\PushSender;
use Illuminate\Support\Collection;

/**
 * The default: pushes configured off. The mobile app falls back to
 * foreground polling.
 */
class NullSender implements PushSender
{
    public function send(PushMessage $message, Collection $devices): void
    {
        // Intentionally nothing.
    }
}
