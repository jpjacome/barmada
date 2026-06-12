<?php

namespace App\Actions\Tables;

use App\Exceptions\DomainActionException;
use App\Models\Table;
use App\Models\TableSessionRequest;

/**
 * Approves a table that guests scanned while it was closed [F-1]:
 * opens it (the model hook creates the session and rotates the token),
 * adopts the device requests recorded while no session existed into the
 * fresh session, and approves the FIRST requester so the waiting guest
 * gets in without rescanning. Later requesters stay pending and appear
 * in the open table's client list.
 */
class ApproveTableAndAdoptRequests
{
    /**
     * @throws DomainActionException
     */
    public function handle(Table $table): Table
    {
        if ($table->status !== 'pending_approval') {
            throw new DomainActionException(__('Table is not awaiting approval.'));
        }

        $table->status = 'open';
        $table->save();

        $session = $table->sessions()
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();

        if ($session) {
            $orphans = TableSessionRequest::whereNull('table_session_id')
                ->where('table_id', $table->id)
                ->where('status', 'pending')
                ->whereDate('created_at', now()->toDateString())
                ->orderBy('requested_at')
                ->orderBy('id')
                ->get();

            foreach ($orphans as $index => $request) {
                $request->table_session_id = $session->id;
                if ($index === 0) {
                    $request->status = 'approved';
                    $request->approved_at = now();
                }
                $request->save();
            }
        }

        return $table->refresh();
    }
}
