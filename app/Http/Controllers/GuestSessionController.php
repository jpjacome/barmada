<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\Table;
use Illuminate\Http\Request;

/**
 * The guest's "my table" page: orders placed this session, the running
 * bill, and the request-bill / call-waiter buttons. Reached through the
 * same tokenized, device-approved flow as the menu.
 */
class GuestSessionController extends Controller
{
    public function show(Request $request, $unique_token)
    {
        $table = Table::where('unique_token', $unique_token)->first();
        if (! $table || $table->status !== 'open') {
            return response()->view('orders.table-closed', ['table' => $table]);
        }

        $editor = $table->editor;
        if ($editor) {
            app()->setLocale($editor->guestLocale());
        }
        $currency = $editor ? $editor->currencySymbol() : '$';

        $session = $table->currentSession();

        $orders = $session
            ? Order::where('table_id', $table->id)
                ->where('table_session_id', $session->id)
                ->with(['items.product'])
                ->orderBy('created_at')
                ->get()
            : collect();

        // Cancelled orders stay in the list but are excluded from the bill
        // [#12]; the shared read model applies that rule and carries the
        // tax breakdown the guest is entitled to see.
        $bill = \App\Support\TableBill::build($table);
        $total = $bill['total'];
        $paid = $bill['paid'];

        $openRequests = $session
            ? ServiceRequest::where('table_session_id', $session->id)
                ->where('status', 'pending')
                ->pluck('type')
                ->all()
            : [];

        return view('orders.session', [
            'table' => $table,
            'orders' => $orders,
            'currency' => $currency,
            'total' => $total,
            'paid' => $paid,
            'left' => $bill['left'],
            'bill' => $bill,
            'unique_token' => $unique_token,
            'openRequests' => $openRequests,
        ]);
    }

    /**
     * Guest signal: bring the bill / call a waiter. Stateless (no CSRF
     * session), gated by device approval and rate limited at the route.
     */
    public function requestService(Request $request, $unique_token)
    {
        $table = Table::where('unique_token', $unique_token)->first();
        if (! $table || $table->status !== 'open') {
            return redirect()->route('orders.waiting-approval');
        }

        $validated = $request->validate([
            'type' => 'required|in:bill,waiter',
        ]);

        $session = $table->currentSession();

        if ($session) {
            // One open request per type per session — repeat taps are a no-op.
            $serviceRequest = ServiceRequest::firstOrCreate([
                'table_session_id' => $session->id,
                'type' => $validated['type'],
                'status' => 'pending',
            ], [
                'table_id' => $table->id,
                'editor_id' => $table->editor_id,
            ]);

            // Push only the FIRST tap of a type per session — the dedup
            // above already absorbs repeats.
            if ($serviceRequest->wasRecentlyCreated) {
                \App\Support\Push::venue($table->editor_id, 'service.requested', [
                    'table_number' => $table->table_number,
                    'type' => $validated['type'],
                ]);
            }
        }

        return redirect()->route('order.session', ['unique_token' => $unique_token]);
    }
}
