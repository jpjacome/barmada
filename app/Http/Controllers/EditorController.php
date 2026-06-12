<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EditorController extends Controller
{
    /**
     * Delete an establishment and ALL of its data (admin only; the route
     * is admin-gated and this guards again for defense in depth).
     *
     * Deletion follows foreign-key dependency order: order items before
     * orders, orders before tables, session requests before sessions,
     * sessions before tables.
     */
    public function destroy($id)
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $editor = User::where('is_editor', true)->where('is_admin', false)->findOrFail($id);

        DB::transaction(function () use ($editor) {
            $orderIds = Order::acrossEditors()->where('editor_id', $editor->id)->pluck('id');
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::acrossEditors()->where('editor_id', $editor->id)->delete();

            $tableIds = Table::acrossEditors()->where('editor_id', $editor->id)->pluck('id');
            $sessionIds = TableSession::acrossEditors()->where('editor_id', $editor->id)->pluck('id');
            TableSessionRequest::whereIn('table_session_id', $sessionIds)
                ->orWhereIn('table_id', $tableIds)
                ->delete();
            ServiceRequest::acrossEditors()->where('editor_id', $editor->id)->delete();
            TableSession::acrossEditors()->where('editor_id', $editor->id)->delete();
            Table::acrossEditors()->where('editor_id', $editor->id)->delete();

            Product::acrossEditors()->where('editor_id', $editor->id)->delete();
            Category::acrossEditors()->where('editor_id', $editor->id)->delete();
            ActivityLog::acrossEditors()->where('editor_id', $editor->id)->delete();

            User::where('is_staff', true)->where('editor_id', $editor->id)->delete();

            $editor->delete();
        });

        return redirect()->route('admin.editors')
            ->with('status', 'Establishment and all related data deleted successfully.');
    }
}
