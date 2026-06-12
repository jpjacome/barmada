<?php

namespace App\Push;

use App\Push\Contracts\PushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The hosted Barmada Push Relay (proposal §5.3, Home Assistant model):
 * a self-hosted server can notify the official store app without owning
 * Firebase keys. PAYLOAD-LIGHT BY DESIGN — the relay sees device tokens
 * and an event name, never order contents, regardless of the
 * include_content setting.
 *
 * The relay service itself ships separately; this driver defines the
 * server-side contract now so it lands without another release.
 */
class RelaySender implements PushSender
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $key,
    ) {}

    public function send(PushMessage $message, Collection $devices): void
    {
        if (! $this->url) {
            Log::warning('Push: relay driver selected but PUSH_RELAY_URL is not configured.');

            return;
        }

        try {
            $response = Http::withToken((string) $this->key)
                ->timeout(5)
                ->post(rtrim($this->url, '/').'/v1/push', [
                    'event' => $message->event,
                    'tokens' => $devices->pluck('fcm_token')->values()->all(),
                ]);

            if ($response->failed()) {
                Log::warning('Push: relay send failed', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Push: relay send error: '.$e->getMessage());
        }
    }
}
