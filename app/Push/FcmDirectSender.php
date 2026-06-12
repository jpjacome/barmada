<?php

namespace App\Push;

use App\Models\ApiDevice;
use App\Push\Contracts\PushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct FCM HTTP v1 delivery using the venue's OWN Firebase service
 * account — no SDK dependency: the Google OAuth assertion is a plain
 * RS256 JWT signed with openssl and exchanged for a short-lived access
 * token (cached).
 *
 * Stale registrations (FCM UNREGISTERED / 404) clear the device's token
 * so dead devices stop consuming sends.
 */
class FcmDirectSender implements PushSender
{
    public function __construct(
        private readonly ?string $projectId,
        private readonly ?string $credentialsPath,
        private readonly bool $includeContent = true,
    ) {}

    public function send(PushMessage $message, Collection $devices): void
    {
        if (! $this->projectId || ! $this->credentialsPath) {
            Log::warning('Push: fcm driver selected but FCM_PROJECT_ID / FCM_CREDENTIALS_PATH are not configured.');

            return;
        }

        $token = $this->accessToken();
        if (! $token) {
            return;
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        foreach ($devices as $device) {
            try {
                $payload = [
                    'message' => [
                        'token' => $device->fcm_token,
                        'data' => $message->data,
                        'android' => ['priority' => 'high'],
                    ],
                ];

                if ($this->includeContent && $message->title) {
                    $payload['message']['notification'] = array_filter([
                        'title' => $message->title,
                        'body' => $message->body,
                    ]);
                }

                $response = Http::withToken($token)
                    ->timeout(5)
                    ->post($endpoint, $payload);

                if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                    // The app was uninstalled or the token rotated away:
                    // retire it so future fan-outs skip this device.
                    $this->forgetToken($device);
                } elseif ($response->failed()) {
                    Log::warning('Push: FCM send failed', [
                        'status' => $response->status(),
                        'device_id' => $device->id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Push: FCM send error: '.$e->getMessage(), ['device_id' => $device->id]);
            }
        }
    }

    /**
     * A cached OAuth2 access token for the firebase.messaging scope.
     */
    private function accessToken(): ?string
    {
        return Cache::remember('barmada.push.fcm_access_token', 3300, function () {
            $account = $this->serviceAccount();
            if (! $account) {
                return null;
            }

            $now = time();
            $claims = [
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $account['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $assertion = $this->signJwt($claims, $account['private_key']);
            if (! $assertion) {
                return null;
            }

            $response = Http::asForm()->timeout(10)->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->failed()) {
                Log::warning('Push: FCM OAuth token exchange failed', ['status' => $response->status()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function serviceAccount(): ?array
    {
        if (! is_readable($this->credentialsPath)) {
            Log::warning("Push: FCM credentials file not readable at {$this->credentialsPath}.");

            return null;
        }

        $account = json_decode((string) file_get_contents($this->credentialsPath), true);

        foreach (['client_email', 'private_key', 'token_uri'] as $key) {
            if (empty($account[$key])) {
                Log::warning("Push: FCM credentials file is missing '{$key}'.");

                return null;
            }
        }

        return $account;
    }

    private function signJwt(array $claims, string $privateKeyPem): ?string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $signingInput = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        $key = openssl_pkey_get_private($privateKeyPem);
        if (! $key || ! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::warning('Push: could not sign the FCM OAuth assertion (invalid private key?).');

            return null;
        }

        return $signingInput.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function forgetToken(ApiDevice $device): void
    {
        $device->forceFill(['fcm_token' => null])->save();
    }
}
