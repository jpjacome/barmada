<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Support\PendingApprovals;

/**
 * The live board in one payload — what the staff app polls while
 * foregrounded (and re-fetches when a push wakes it): pending orders,
 * the device-approval queue, and open service requests.
 *
 * All queries are bounded by EditorScope; admins see across tenants,
 * exactly like the web board.
 */
class BoardController extends Controller
{
    public function __invoke()
    {
        $pendingOrders = Order::where('status', 'pending')
            ->with(['table', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $serviceRequests = ServiceRequest::where('status', 'pending')
            ->with('table')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($request) => [
                'id' => $request->id,
                'type' => $request->type,
                'table_id' => $request->table_id,
                'table_number' => $request->table->table_number ?? $request->table_id,
                'requested_at' => $request->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'pending_orders' => OrderResource::collection($pendingOrders),
            'approval_requests' => PendingApprovals::list(),
            'service_requests' => $serviceRequests,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
