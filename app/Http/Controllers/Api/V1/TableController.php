<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tables\ApproveTableAndAdoptRequests;
use App\Actions\Tables\ArchiveTable;
use App\Actions\Tables\CloseTable;
use App\Actions\Tables\OpenTable;
use App\Actions\Tables\RestoreTable;
use App\Actions\Tables\SaveClientInvoice;
use App\Actions\Tables\SettleTable;
use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\User;
use App\Support\TableBill;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TableController extends Controller
{
    use AuthorizesRequests;

    /**
     * The table grid: active tables (and archived, on request), bounded
     * by EditorScope.
     */
    public function index(Request $request)
    {
        $tables = Table::whereNull('archived_at')
            ->orderBy('table_number')
            ->get()
            ->map(fn (Table $table) => $this->tableSummary($table));

        $payload = ['tables' => $tables];

        if ($request->boolean('include_archived')) {
            $payload['archived'] = Table::whereNotNull('archived_at')
                ->orderBy('table_number')
                ->get()
                ->map(fn (Table $table) => $this->tableSummary($table));
        }

        return response()->json($payload);
    }

    /**
     * The table's current session as the staff app's payment screen needs
     * it: countable orders with per-item paid state, totals, the captured
     * invoice details, and open service requests.
     */
    public function session(Table $table)
    {
        $this->authorize('view', $table);

        return response()->json($this->sessionPayload($table));
    }

    public function open(Table $table, OpenTable $openTable)
    {
        $this->authorize('update', $table);

        $openTable->handle($table);

        return response()->json($this->sessionPayload($table->refresh()));
    }

    /**
     * Explicit close [F-11]. Pass {"settle": true} to mark the remaining
     * balance paid and close in one step (the web's "pay & close").
     */
    public function close(Request $request, Table $table, SettleTable $settleTable, CloseTable $closeTable)
    {
        $this->authorize('update', $table);

        if ($request->boolean('settle')) {
            $settleTable->handle($table);
        }

        $closeTable->handle($table);

        return response()->json(['table' => $this->tableSummary($table->refresh())]);
    }

    /**
     * Approves a pending table and adopts the waiting devices — the
     * first-guest flow [F-1].
     */
    public function approve(Table $table, ApproveTableAndAdoptRequests $approveTable)
    {
        $this->authorize('update', $table);

        $approveTable->handle($table);

        return response()->json($this->sessionPayload($table->refresh()));
    }

    public function settle(Table $table, SettleTable $settleTable)
    {
        $this->authorize('update', $table);

        $settleTable->handle($table);

        return response()->json($this->sessionPayload($table->refresh()));
    }

    public function archive(Table $table, ArchiveTable $archiveTable)
    {
        $this->authorize('update', $table);

        $archiveTable->handle($table);

        return response()->json(['table' => $this->tableSummary($table->refresh())]);
    }

    public function restore(Table $table, RestoreTable $restoreTable)
    {
        $this->authorize('update', $table);

        $restoreTable->handle($table);

        return response()->json(['table' => $this->tableSummary($table->refresh())]);
    }

    /**
     * Client tax-invoice capture for the current session — printed on
     * the bill. [#6]
     */
    public function saveInvoice(Request $request, Table $table, SaveClientInvoice $saveInvoice)
    {
        $this->authorize('update', $table);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:64',
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
        ]);

        $invoice = $saveInvoice->handle($table, $validated);

        return response()->json([
            'invoice' => [
                'name' => $invoice->name,
                'tax_id' => $invoice->tax_id,
                'address' => $invoice->address,
                'email' => $invoice->email,
                'phone' => $invoice->phone,
            ],
        ]);
    }

    /**
     * Resolves a staff QR scan to a table the app can navigate to.
     *
     * Staff phones scan the same QR codes guests do
     * (`/qr-entry/{username}/{table_number}`). The app extracts the
     * username and table_number from the URL and calls this endpoint to
     * get the table's `id` and current `status` for direct navigation.
     *
     * Cross-venue protection: a staff or editor token can only resolve
     * tables that belong to their own venue — mismatched venues 403.
     */
    public function resolveQr(Request $request)
    {
        $validated = $request->validate([
            'username'     => 'required|string|max:255',
            'table_number' => 'required|integer|min:1',
        ]);

        $editor = User::where('username', $validated['username'])
            ->where('is_editor', true)
            ->first();

        if (! $editor) {
            return response()->json(['message' => __('Venue not found.')], 404);
        }

        // Cross-venue guard: the authenticated user must belong to this editor.
        $authEditorId = $request->user()->effectiveEditorId();
        if ($authEditorId !== $editor->id) {
            return response()->json(
                ['message' => __('This QR code belongs to a different venue.')],
                403
            );
        }

        $table = Table::where('editor_id', $editor->id)
            ->where('table_number', (int) $validated['table_number'])
            ->whereNull('archived_at')
            ->first();

        if (! $table) {
            return response()->json(['message' => __('Table not found.')], 404);
        }

        return response()->json(['table' => $this->tableSummary($table)]);
    }

    private function tableSummary(Table $table): array
    {
        return [
            'id' => $table->id,
            'table_number' => $table->table_number,
            'reference' => $table->reference,
            'status' => $table->status,
            'archived_at' => $table->archived_at?->toIso8601String(),
        ];
    }

    private function sessionPayload(Table $table): array
    {
        $bill = TableBill::build($table);

        $serviceRequests = $bill['session']
            ? \App\Models\ServiceRequest::where('table_session_id', $bill['session']->id)
                ->where('status', 'pending')
                ->get()
                ->map(fn ($request) => [
                    'id' => $request->id,
                    'type' => $request->type,
                    'requested_at' => $request->created_at?->toIso8601String(),
                ])
            : collect();

        return [
            'table' => $this->tableSummary($table),
            'session' => $bill['session'] ? [
                'id' => $bill['session']->id,
                'session_number' => $bill['session']->session_number,
                'status' => $bill['session']->status,
                'opened_at' => $bill['session']->opened_at?->toIso8601String(),
            ] : null,
            'orders' => $bill['orders'],
            'totals' => [
                'total' => round((float) $bill['total'], 2),
                'paid' => round((float) $bill['paid'], 2),
                'left' => round((float) $bill['left'], 2),
            ],
            'invoice' => $bill['invoice'] ? [
                'name' => $bill['invoice']->name,
                'tax_id' => $bill['invoice']->tax_id,
                'address' => $bill['invoice']->address,
                'email' => $bill['invoice']->email,
                'phone' => $bill['invoice']->phone,
            ] : null,
            'service_requests' => $serviceRequests,
        ];
    }
}
