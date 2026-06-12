<?php

namespace App\Actions\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\ClientInvoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an establishment (editor tenant) and ALL of its data in one
 * FK-ordered transaction. Platform-admin only — authorization belongs
 * to the calling boundary.
 *
 * Also purges client_invoices (added after the original deletion was
 * written): these rows carry guest PII (names, tax ids) and have no FK
 * cascade, so they would silently outlive their tenant otherwise.
 * Staff users' api_devices cascade through the users FK.
 */
class DeleteEstablishment
{
    public function handle(User $editor): void
    {
        DB::transaction(function () use ($editor) {
            $orderIds = Order::acrossEditors()->where('editor_id', $editor->id)->pluck('id');
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::acrossEditors()->where('editor_id', $editor->id)->delete();

            $tableIds = Table::acrossEditors()->where('editor_id', $editor->id)->pluck('id');
            $sessionIds = TableSession::acrossEditors()->where('editor_id', $editor->id)->pluck('id');
            TableSessionRequest::whereIn('table_session_id', $sessionIds)
                ->orWhereIn('table_id', $tableIds)
                ->delete();
            ClientInvoice::acrossEditors()->where('editor_id', $editor->id)->delete();
            ServiceRequest::acrossEditors()->where('editor_id', $editor->id)->delete();
            TableSession::acrossEditors()->where('editor_id', $editor->id)->delete();
            Table::acrossEditors()->where('editor_id', $editor->id)->delete();

            Product::acrossEditors()->where('editor_id', $editor->id)->delete();
            Category::acrossEditors()->where('editor_id', $editor->id)->delete();
            ActivityLog::acrossEditors()->where('editor_id', $editor->id)->delete();

            User::where('is_staff', true)->where('editor_id', $editor->id)->delete();

            $editor->delete();
        });
    }
}
