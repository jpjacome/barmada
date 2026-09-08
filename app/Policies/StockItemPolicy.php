<?php

namespace App\Policies;

use App\Models\StockItem;
use App\Models\User;

class StockItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->effectiveEditorId() !== null;
    }

    public function view(User $user, StockItem $item): bool
    {
        return $this->ownsTenant($user, $item->editor_id);
    }

    /** Receiving, counting and waste are daily staff work. */
    public function create(User $user): bool
    {
        return $user->effectiveEditorId() !== null;
    }

    public function update(User $user, StockItem $item): bool
    {
        return $this->ownsTenant($user, $item->editor_id);
    }

    /** Only the owner retires items or edits recipes. */
    public function manage(User $user, StockItem $item): bool
    {
        return (bool) $user->is_editor && $this->ownsTenant($user, $item->editor_id);
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
