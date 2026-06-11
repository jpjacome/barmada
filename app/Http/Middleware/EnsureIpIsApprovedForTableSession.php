<?php

namespace App\Http\Middleware;

use App\Models\Table;
use Closure;
use Illuminate\Http\Request;

class EnsureIpIsApprovedForTableSession
{
    /**
     * Gate guest QR-flow requests behind the table-session approval flow.
     *
     * The table is resolved from the route's unique_token (the QR order
     * routes) or an explicit table id. The request proceeds only when the
     * table is open and either:
     *  - the caller is an authenticated member of the table's tenant
     *    (or the admin), or
     *  - the caller's device (IP) holds an approved request on the
     *    table's current open session.
     *
     * Designed to run on stateless guest routes: no session is required.
     */
    public function handle(Request $request, Closure $next)
    {
        $table = $this->resolveTable($request);

        if (! $table || $table->status !== 'open') {
            abort(403, 'Table is not open.');
        }

        $user = $request->user();

        if ($user && ($user->is_admin || $user->effectiveEditorId() === $table->editor_id)) {
            return $next($request);
        }

        $session = $table->sessions()
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();

        if (! $session) {
            abort(403, 'No open session for this table.');
        }

        $approved = $session->sessionRequests()
            ->where('ip_address', $request->ip())
            ->where('status', 'approved')
            ->exists();

        if (! $approved) {
            abort(403, 'Your device is not approved for this table session.');
        }

        return $next($request);
    }

    /**
     * Resolve the target table without tenant scoping: this middleware
     * performs its own authorization for guest traffic, where no user
     * context exists yet.
     */
    private function resolveTable(Request $request): ?Table
    {
        $token = $request->route('unique_token');

        if ($token) {
            return Table::acrossEditors()->where('unique_token', $token)->first();
        }

        $tableId = $request->route('table') ?? $request->input('table_id');

        if ($tableId instanceof Table) {
            return $tableId;
        }

        return $tableId ? Table::acrossEditors()->find($tableId) : null;
    }
}
