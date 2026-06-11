<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Admins pass every ability check.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, Product $product): bool
    {
        return $this->ownsTenant($user, $product->editor_id);
    }

    public function create(User $user): bool
    {
        return $user->effectiveEditorId() !== null;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->ownsTenant($user, $product->editor_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->ownsTenant($user, $product->editor_id);
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null
            && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
