<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\DeleteEstablishment;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Platform administration: the establishments (editor tenants) running
 * on this server. Admin only. Impersonation stays web-only by design
 * (session-based; too sharp a tool for a bearer-token surface).
 */
class EstablishmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);

        return response()->json([
            'establishments' => User::where('is_editor', true)
                ->withCount(['tables', 'products', 'orders'])
                ->orderBy('created_at')
                ->get()
                ->map(fn (User $editor) => [
                    'id' => $editor->id,
                    'username' => $editor->username,
                    'name' => $editor->name,
                    'business_name' => $editor->business_name,
                    'email' => $editor->email,
                    'created_at' => $editor->created_at?->toIso8601String(),
                    'tables_count' => (int) $editor->tables_count,
                    'products_count' => (int) $editor->products_count,
                    'orders_count' => (int) $editor->orders_count,
                ]),
        ]);
    }

    /**
     * Deletes an establishment and ALL its data (FK-ordered transaction,
     * including client invoices and staff accounts). Requires an explicit
     * {"confirm": true} — there is no undo.
     */
    public function destroy(Request $request, int $id, DeleteEstablishment $deleteEstablishment)
    {
        abort_unless($request->user()?->is_admin, 403);

        if (! $request->boolean('confirm')) {
            return response()->json([
                'message' => __('Confirmation required: pass "confirm": true to delete this establishment and all of its data.'),
            ], 422);
        }

        $editor = User::where('is_editor', true)->where('is_admin', false)->findOrFail($id);

        $deleteEstablishment->handle($editor);

        return response()->json(['message' => __('Establishment and all related data deleted.')]);
    }
}
