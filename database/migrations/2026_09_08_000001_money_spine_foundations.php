<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundations the fiscal and inventory work will stand on.
 *
 *  - activity_logs.user_id: the model has declared this column (and a
 *    user() relation) since the beginning, but no migration ever added
 *    it, so every payment log recorded an amount with no actor. For any
 *    cash-handling question — and for a fiscal audit trail — "who took
 *    the money" is the first thing anyone asks.
 *  - order_items.paid_at / paid_by / payment_method: payment state was a
 *    bare boolean. SRI's factura needs a forma de pago; a manager needs
 *    to know when and by whom an item was ticked.
 *  - Indexes on every hot path the board, the bill and analytics query
 *    every few seconds. Under SQLite (the default connection) foreign
 *    keys create no index at all.
 *  - Uniqueness where the code already assumed it: one session number per
 *    table per day (the model hook read-then-writes it), one client
 *    invoice per session (SaveClientInvoice updateOrCreate()s on it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('order_id')->index();
            }
            $table->index(['editor_id', 'type', 'created_at'], 'activity_logs_editor_type_created_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('is_paid');
            $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            // cash | card | transfer | other — mapped to SRI formaPago later.
            $table->string('payment_method', 16)->nullable()->after('paid_by');
            $table->index(['order_id', 'is_paid'], 'order_items_order_paid_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['editor_id', 'status', 'created_at'], 'orders_editor_status_created_idx');
            $table->index(['editor_id', 'created_at'], 'orders_editor_created_idx');
            $table->index(['table_id', 'table_session_id'], 'orders_table_session_idx');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->index(['editor_id', 'status', 'created_at'], 'service_requests_editor_status_created_idx');
        });

        // Session numbering: renumber any duplicates before making the
        // invariant real. Production holds test data only at this point,
        // but the migration must be safe anywhere.
        $duplicates = DB::table('table_sessions')
            ->select('table_id', 'date', 'session_number', DB::raw('COUNT(*) as c'))
            ->groupBy('table_id', 'date', 'session_number')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('table_sessions')
                ->where('table_id', $dup->table_id)
                ->where('date', $dup->date)
                ->where('session_number', $dup->session_number)
                ->orderBy('opened_at')->orderBy('id')
                ->get();

            $next = (int) DB::table('table_sessions')
                ->where('table_id', $dup->table_id)
                ->where('date', $dup->date)
                ->max('session_number');

            foreach ($rows->slice(1) as $row) {
                DB::table('table_sessions')->where('id', $row->id)->update(['session_number' => ++$next]);
            }
        }

        Schema::table('table_sessions', function (Blueprint $table) {
            $table->unique(['table_id', 'date', 'session_number'], 'table_sessions_table_date_number_unique');
            $table->index(['table_id', 'status', 'opened_at'], 'table_sessions_table_status_opened_idx');
        });

        // One invoice per session: keep the most recent row.
        $invoiceDupes = DB::table('client_invoices')
            ->select('table_session_id', DB::raw('COUNT(*) as c'))
            ->groupBy('table_session_id')
            ->having('c', '>', 1)
            ->pluck('table_session_id');

        foreach ($invoiceDupes as $sessionId) {
            $keep = DB::table('client_invoices')->where('table_session_id', $sessionId)->orderByDesc('updated_at')->orderByDesc('id')->value('id');
            DB::table('client_invoices')->where('table_session_id', $sessionId)->where('id', '!=', $keep)->delete();
        }

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->unique('table_session_id', 'client_invoices_session_unique');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', fn (Blueprint $t) => $t->dropUnique('client_invoices_session_unique'));
        Schema::table('table_sessions', function (Blueprint $t) {
            $t->dropUnique('table_sessions_table_date_number_unique');
            $t->dropIndex('table_sessions_table_status_opened_idx');
        });
        Schema::table('service_requests', fn (Blueprint $t) => $t->dropIndex('service_requests_editor_status_created_idx'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_editor_status_created_idx');
            $t->dropIndex('orders_editor_created_idx');
            $t->dropIndex('orders_table_session_idx');
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex('order_items_order_paid_idx');
            $t->dropColumn(['paid_at', 'paid_by', 'payment_method']);
        });
        Schema::table('activity_logs', function (Blueprint $t) {
            $t->dropIndex('activity_logs_editor_type_created_idx');
            $t->dropColumn('user_id');
        });
    }
};
