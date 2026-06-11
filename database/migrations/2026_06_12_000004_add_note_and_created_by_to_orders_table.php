<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - note: free-text request attached to an order ("no ice", "split plates").
 * - created_by: the staff user who entered a manual order (null for guest
 *   orders) — gives the staff-orders analytics a real grouping column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('status');
            $table->unsignedBigInteger('created_by')->nullable()->after('editor_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropColumn(['note', 'created_by']);
        });
    }
};
