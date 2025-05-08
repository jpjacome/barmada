<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;

class EnsureIpIsApprovedForTableSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $tableId = $request->route('table') ?? $request->input('table_id');
        if (!$tableId) {
            return abort(403, 'Table not specified.');
        }
        $table = Table::find($tableId);
        if (!$table || $table->status !== 'open') {
            return abort(403, 'Table is not open.');
        }
        $session = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
        if (!$session) {
            return abort(403, 'No open session for this table.');
        }
        $ip = $request->ip();
        $approved = $session->sessionRequests()->where('ip_address', $ip)->where('status', 'approved')->exists();
        if (!$approved) {
            return abort(403, 'Your device is not approved for this table session.');
        }
        return $next($request);
    }
}
