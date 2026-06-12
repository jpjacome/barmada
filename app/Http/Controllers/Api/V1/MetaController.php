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
        return response()->json([
            'product' => 'barmada',
            'name' => config('app.name'),
            'api' => [
                'version' => self::API_VERSION,
                'auth' => 'sanctum-bearer',
            ],
            'features' => [
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
            ],
            'min_app_version' => null,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
