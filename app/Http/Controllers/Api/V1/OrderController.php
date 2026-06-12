<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\SettleOrder;
use App\Actions\Orders\ToggleItemPaid;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Order list with optional filters. Implicit tenancy via EditorScope;
     * admins see all tenants.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,delivered,cancelled',
            'table_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Order::with(['table', 'items.product'])->orderBy('created_at', 'desc');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['table_id'])) {
            $query->where('table_id', (int) $validated['table_id']);
        }

        $orders = $query->paginate((int) ($validated['per_page'] ?? 25));

        return OrderResource::collection($orders);
    }

    /**
     * Manual order entry (staff/editor/admin) — the API twin of the web
     * order form, through the same CreateOrder action as the guest flow.
     */
    public function store(Request $request, CreateOrder $createOrder)
    {
        $validated = $request->validate([
            'table_id' => 'required|integer',
            'products' => 'required|array|min:1|max:50',
            'products.*' => 'integer|min:0|max:99',
            'note' => 'nullable|string|max:280',
        ]);

        $this->authorize('create', Order::class);

        // Resolves through EditorScope: cross-tenant tables 404 for
        // editors and staff; admins reach any tenant's table.
        $table = Table::findOrFail($validated['table_id']);

        $session = TableSession::where('table_id', $table->id)
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();

        if (! $session) {
            return response()->json([
                'message' => __('No open session for this table. Please open the table first.'),
            ], 422);
        }

        $order = $createOrder->handle(
            $table,
            $session,
            $validated['products'],
            $validated['note'] ?? null,
            $request->user(),
        );

        return (new OrderResource($order->load('table')))->response()->setStatusCode(201);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['table', 'items.product']));
    }

    /**
     * Status transitions: deliver, un-deliver, cancel — with the board's
     * rules (cancelled is final, cancel from pending only).
     */
    public function updateStatus(Request $request, Order $order, ChangeOrderStatus $changeStatus)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,delivered,cancelled',
        ]);

        $this->authorize('update', $order);

        $changeStatus->handle($order, $validated['status']);

        return new OrderResource($order->refresh()->load(['table', 'items.product']));
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $order->delete();

        return response()->json(['message' => __('Order deleted.')]);
    }

    /**
     * The payment-ticking endpoint: toggles one item's paid state (tap as
     * guests pay cash), auto-delivering the order when fully paid.
     */
    public function toggleItemPaid(Request $request, Order $order, ToggleItemPaid $toggleItemPaid)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'item_index' => 'required|integer|min:0',
        ]);

        $this->authorize('update', $order);

        $item = $toggleItemPaid->handle($order, (int) $validated['product_id'], (int) $validated['item_index']);

        if (! $item) {
            return response()->json(['message' => __('Item not found on this order.')], 404);
        }

        return new OrderResource($order->refresh()->load(['table', 'items.product']));
    }

    public function settle(Order $order, SettleOrder $settleOrder)
    {
        $this->authorize('update', $order);

        $settleOrder->handle($order);

        return new OrderResource($order->refresh()->load(['table', 'items.product']));
    }
}
