<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

/**
 * Public server discovery for the mobile app's add-server screen: a
 * self-hosted Barmada announces what it is and what this API supports,
 * so old servers and new apps can degrade gracefully.
 */
class MetaController extends Controller
{
    public const API_VERSION = 1;

    public function __invoke()
    {
        $features = [
            'board',
            'orders',
            'order-notes',
            'item-payments',
            'tables',
            'sessions',
            'client-invoices',
            'approvals',
            'service-requests',
            'product-availability',
            'devices',
            'catalog-management',
            'staff-management',
            'settings',
            'analytics',
        ];

        // Advertised only when a delivery driver is configured, so the
        // app knows whether to expect wake-ups or rely on polling.
        if (config('push.driver', 'none') !== 'none') {
            $features[] = 'push';
        }

        return response()->json([
            'product' => 'barmada',
            'name' => config('app.name'),
            'api' => [
                'version' => self::API_VERSION,
                'auth' => 'sanctum-bearer',
            ],
            'features' => $features,
            'min_app_version' => null,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
