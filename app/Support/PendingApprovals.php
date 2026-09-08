<?php

namespace App\Support;

use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use Illuminate\Support\Carbon;

/**
 * The device-approval queue as one flat list, mirroring the staff board:
 *
 *  - "first_guest": requests recorded while the table was still closed
 *    (no session yet) — approving the TABLE adopts these [F-1].
 *  - "additional_guest": pending requests on an open table's current
 *    session — approved individually.
 *
 * Queries are bounded by EditorScope on Table; admins see all tenants.
 * Four queries regardless of table count — this list is polled by the
 * staff app and the web board every few seconds.
 */
class PendingApprovals
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list(): array
    {
        $tables = Table::whereIn('status', ['pending_approval', 'open'])->get();

        $sessions = TableSession::whereIn('table_id', $tables->where('status', 'open')->pluck('id'))
            ->whereIn('status', ['open', 'reopened'])
            ->orderByDesc('opened_at')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');

        $sessionPending = TableSessionRequest::whereIn('table_session_id', $sessions->pluck('id'))
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->groupBy('table_session_id');

        $orphanPending = TableSessionRequest::whereNull('table_session_id')
            ->whereIn('table_id', $tables->where('status', 'pending_approval')->pluck('id'))
            ->where('status', 'pending')
            ->whereDate('created_at', now()->toDateString())
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get()
            ->groupBy('table_id');

        $rows = [];

        foreach ($tables as $table) {
            if ($table->status === 'pending_approval') {
                $pending = $orphanPending->get($table->id, collect());
                $scope = 'first_guest';
            } else {
                $session = $sessions->get($table->id);
                $pending = $session ? $sessionPending->get($session->id, collect()) : collect();
                $scope = 'additional_guest';
            }

            foreach ($pending as $request) {
                // requested_at is uncast (string) on TableSessionRequest;
                // normalize either source to ISO-8601.
                $requestedAt = $request->requested_at ?? $request->created_at;
                if ($requestedAt && ! $requestedAt instanceof \Carbon\CarbonInterface) {
                    $requestedAt = Carbon::parse($requestedAt);
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
