<?php

namespace App\Actions\Approvals;

use App\Exceptions\DomainActionException;
use App\Models\TableSession;
use App\Models\TableSessionRequest;

/**
 * Approves a guest device on an OPEN table's current session. Requests
 * recorded while the table was still closed (no session yet) are adopted
 * through ApproveTableAndAdoptRequests instead.
 *
 * TableSessionRequest carries no tenant column of its own, so ownership
 * is enforced by resolving the session through EditorScope: sessions
 * outside the caller's tenant resolve to null and the request is
 * rejected as not approvable.
 */
class ApproveSessionRequest
{
    /**
     * @throws DomainActionException
     */
    public function handle(TableSessionRequest $request): TableSessionRequest
    {
        $approvable = $request->status === 'pending'
            && $request->table_session_id
            && TableSession::find($request->table_session_id);

        if (! $approvable) {
            throw new DomainActionException(__('Request cannot be approved.'));
        }

        $request->status = 'approved';
        $request->approved_at = now();
        $request->save();

        return $request;
    }
}
