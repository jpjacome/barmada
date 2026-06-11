<?php

namespace App\Policies;

use App\Models\Table;
use App\Models\User;

class TablePolicy
{
    /**
     * Admins pass every ability check.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, Table $table): bool
    {
        return $this->ownsTenant($user, $table->editor_id);
    }

    public function create(User $user): bool
    {
        return $user->effectiveEditorId() !== null;
    }

    public function update(User $user, Table $table): bool
    {
        return $this->ownsTenant($user, $table->editor_id);
    }

    public function delete(User $user, Table $table): bool
    {
        return $this->ownsTenant($user, $table->editor_id);
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null
            && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
