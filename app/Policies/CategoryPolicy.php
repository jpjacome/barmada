<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Admins pass every ability check.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, Category $category): bool
    {
        return $this->ownsTenant($user, $category->editor_id);
    }

    public function create(User $user): bool
    {
        return $user->effectiveEditorId() !== null;
    }

    public function update(User $user, Category $category): bool
    {
        return $this->ownsTenant($user, $category->editor_id);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->ownsTenant($user, $category->editor_id);
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null
            && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
