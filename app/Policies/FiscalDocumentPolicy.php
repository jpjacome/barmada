<?php

namespace App\Policies;

use App\Models\FiscalDocument;
use App\Models\Table;
use App\Models\User;

class FiscalDocumentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, FiscalDocument $document): bool
    {
        return $this->ownsTenant($user, $document->editor_id);
    }

    /**
     * Issuing binds the venue legally; editors and their staff may do it
     * for their own tables.
     */
    public function issue(User $user, Table $table): bool
    {
        return $this->ownsTenant($user, $table->editor_id);
    }

    private function ownsTenant(User $user, ?int $editorId): bool
    {
        return $editorId !== null
            && (int) $user->effectiveEditorId() === (int) $editorId;
    }
}
