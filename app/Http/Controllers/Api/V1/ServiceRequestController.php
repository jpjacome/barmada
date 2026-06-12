<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ServiceRequests\ResolveServiceRequest;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

/**
 * Guest service requests (bring the bill / call a waiter) on the staff
 * side. Implicit binding resolves through EditorScope, so cross-tenant
 * requests 404 — the same containment the web board relies on.
 */
class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,done,all',
        ]);

        $query = ServiceRequest::with('table')->orderBy('created_at');

        $status = $validated['status'] ?? 'pending';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json([
            'service_requests' => $query->limit(100)->get()->map(fn ($row) => [
                'id' => $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'table_id' => $row->table_id,
                'table_number' => $row->table->table_number ?? $row->table_id,
                'requested_at' => $row->created_at?->toIso8601String(),
                'resolved_at' => $row->resolved_at?->toIso8601String(),
            ]),
        ]);
    }

    public function done(Request $request, ServiceRequest $serviceRequest, ResolveServiceRequest $resolve)
    {
        $resolve->handle($serviceRequest, $request->user());

        return response()->json([
            'service_request' => [
                'id' => $serviceRequest->id,
                'type' => $serviceRequest->type,
                'status' => $serviceRequest->status,
                'resolved_at' => $serviceRequest->resolved_at?->toIso8601String(),
            ],
        ]);
    }
}
