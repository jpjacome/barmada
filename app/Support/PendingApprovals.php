<?php

namespace App\Support;

use App\Models\Table;
use App\Models\TableSessionRequest;

/**
 * The device-approval queue as one flat list, mirroring the staff board:
 *
 *  - "first_guest": requests recorded while the table was still closed
 *    (no session yet) — approving the TABLE adopts these [F-1].
 *  - "additional_guest": pending requests on an open table's current
 *    session — approved individually.
 *
 * Queries are bounded by EditorScope on Table; admins see all tenants.
 */
class PendingApprovals
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list(): array
    {
        $rows = [];

        $tables = Table::whereIn('status', ['pending_approval', 'open'])->get();

        foreach ($tables as $table) {
            if ($table->status === 'pending_approval') {
                $pending = TableSessionRequest::whereNull('table_session_id')
                    ->where('table_id', $table->id)
                    ->where('status', 'pending')
                    ->whereDate('created_at', now()->toDateString())
                    ->orderBy('requested_at')
                    ->orderBy('id')
                    ->get();
                $scope = 'first_guest';
            } else {
                $session = $table->sessions()
                    ->whereIn('status', ['open', 'reopened'])
                    ->latest('opened_at')
                    ->first();

                $pending = $session
                    ? $session->sessionRequests()->where('status', 'pending')->orderBy('id')->get()
                    : collect();
                $scope = 'additional_guest';
            }

            foreach ($pending as $request) {
                // requested_at is uncast (string) on TableSessionRequest;
                // normalize either source to ISO-8601.
                $requestedAt = $request->requested_at ?? $request->created_at;
                if ($requestedAt && ! $requestedAt instanceof \Carbon\CarbonInterface) {
                    $requestedAt = \Illuminate\Support\Carbon::parse($requestedAt);
                }

                $rows[] = [
                    'id' => $request->id,
                    'scope' => $scope,
                    'table_id' => $table->id,
                    'table_number' => $table->table_number,
                    'table_status' => $table->status,
                    'requested_at' => $requestedAt?->toIso8601String(),
                ];
            }
        }

        return $rows;
    }
}
