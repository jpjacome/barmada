<?php

namespace App\Actions\ServiceRequests;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * One-tap "Done" on a guest service request (bring the bill / call a
 * waiter). Idempotent: resolving an already-resolved request is a no-op,
 * so a double tap or a poll race never errors.
 */
class ResolveServiceRequest
{
    public function handle(ServiceRequest $request, User $actor): ServiceRequest
    {
        if ($request->status === 'pending') {
            $request->status = 'done';
            $request->resolved_at = now();
            $request->resolved_by = $actor->id;
            $request->save();
        }

        return $request;
    }
}
