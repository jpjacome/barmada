<?php

namespace App\Models\Concerns;

use App\Models\Scopes\EditorScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enforced tenancy for models owned by an editor.
 *
 * Applies the EditorScope global scope (queries are constrained to the
 * authenticated user's tenant) and assigns editor_id automatically on
 * create when the caller did not set one.
 */
trait BelongsToEditor
{
    protected static function bootBelongsToEditor(): void
    {
        static::addGlobalScope(new EditorScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('editor_id') !== null) {
                return;
            }

            $user = Auth::user();

            if ($user && ! $user->is_admin) {
                $model->setAttribute('editor_id', $user->effectiveEditorId());
            }
        });
    }

    /**
     * Query across all tenants. For admin contexts only — callers must
     * authorize before using this.
     */
    public static function acrossEditors(): Builder
    {
        return static::query()->withoutGlobalScope(EditorScope::class);
    }

    /**
     * Constrain a query to one editor explicitly (admin contexts).
     */
    public function scopeForEditor(Builder $query, int $editorId): Builder
    {
        return $query
            ->withoutGlobalScope(EditorScope::class)
            ->where($query->getModel()->qualifyColumn('editor_id'), $editorId);
    }
}
