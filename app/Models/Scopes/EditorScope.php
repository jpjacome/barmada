<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains every query on tenant-owned models to the authenticated
 * user's tenant (editor).
 *
 * - Admins: explicit bypass — admin screens operate across tenants and
 *   apply their own filters.
 * - Editors: constrained to records where editor_id = their own id.
 * - Staff: constrained to their editor's tenant (users.editor_id).
 * - Authenticated users with no tenant: denied by default (no rows).
 * - Guests: not constrained here; guest flows never query tenant data
 *   directly — they resolve records through table session tokens and
 *   route-level controls.
 */
class EditorScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->is_admin) {
            return;
        }

        $editorId = $user->effectiveEditorId();

        if ($editorId === null) {
            // Default deny: a non-admin user without a tenant sees nothing.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('editor_id'), $editorId);
    }
}
