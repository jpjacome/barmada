<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [#5] Tables with order history cannot be hard-deleted (orders.table_id
 * is a no-cascade FK and the sales history must survive) — archiving
 * hides them from the grid and the QR flow while keeping reporting joins
 * intact.
 *
 * [#17] The tables.orders counter was meaningless (only ever incremented
 * on staff-entered orders) and derivable — dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
        });

        if (Schema::hasColumn('tables', 'orders')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->dropColumn('orders');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('archived_at');
            $table->integer('orders')->default(0);
        });
    }
};
