<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Admins pass every ability check.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, Order $order): bool
    {
        return $this->ownsTenant($user, $order->editor_id);
    }

    public function create(User $user): bool
    {
        return $user->effectiveEditorId() !== null;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->ownsTenant($user, $order->editor_id);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->ownsTenant($user, $order->editor_id);
    }

    /**
     * Bulk-clearing orders is reserved for editors (their own tenant via
     * EditorScope) and admins (before hook).
     */
    public function deleteAll(User $user): bool
    {
        return (bool) $user->is_editor;
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null
            && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
