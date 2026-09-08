<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Guest service requests (bring the bill / call a waiter) belong to the
 * venue they were raised in. Resolving one used to rely on the global
 * scope alone; a single acrossEditors() upstream would have opened it.
 */
class ServiceRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        return $this->ownsTenant($user, $request->editor_id);
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        return $this->ownsTenant($user, $request->editor_id);
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null
            && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
