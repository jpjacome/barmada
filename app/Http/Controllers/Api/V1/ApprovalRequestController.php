<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Approvals\ApproveSessionRequest;
use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Support\PendingApprovals;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ApprovalRequestController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return response()->json(['approval_requests' => PendingApprovals::list()]);
    }

    /**
     * Approves an additional guest on an open table's session.
     *
     * TableSessionRequest carries no tenant column, so tenancy is enforced
     * by resolving its session and table THROUGH EditorScope before
     * authorizing — cross-tenant requests 404. First-guest requests
     * (no session yet) are adopted via POST /tables/{table}/approve.
     */
    public function approve(int $id, ApproveSessionRequest $approveRequest)
    {
        $request = TableSessionRequest::find($id);

        if (! $request) {
            abort(404);
        }

        if ($request->table_session_id) {
            $session = TableSession::find($request->table_session_id);
            $table = $session ? Table::find($session->table_id) : null;
        } else {
            $table = $request->table_id ? Table::find($request->table_id) : null;
        }

        if (! $table) {
            // Out of tenant (or dangling) — invisible to this caller.
            abort(404);
        }

        $this->authorize('update', $table);

        $approveRequest->handle($request);

        return response()->json([
            'approval_request' => [
                'id' => $request->id,
                'status' => $request->status,
                'approved_at' => $request->approved_at,
            ],
        ]);
    }
}
