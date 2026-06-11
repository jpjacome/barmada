<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Editors may create staff accounts for their own tenant.
     */
    public function createStaff(User $actor): bool
    {
        return (bool) $actor->is_editor;
    }

    /**
     * An editor may manage (update, reset credentials of, delete) only
     * staff accounts that belong to their own tenant. Admin and editor
     * accounts are never manageable through staff tooling.
     */
    public function manageStaff(User $actor, User $target): bool
    {
        return $actor->is_editor
            && (bool) $target->is_staff
            && ! $target->is_admin
            && $target->editor_id !== null
            && (int) $target->editor_id === (int) $actor->id;
    }
}
