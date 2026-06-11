<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce per-tenant uniqueness of table numbers.
 *
 * Table numbers are only meaningful within a single editor's tenant, so the
 * uniqueness constraint is the composite (editor_id, table_number) rather than
 * a global one. Rows with a NULL editor_id (admin-owned) are not constrained,
 * which matches SQL's treatment of NULLs in unique indexes.
 *
 * NOTE: if a deployment already contains duplicate (editor_id, table_number)
 * pairs, those must be de-duplicated before this migration can run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->unique(['editor_id', 'table_number'], 'tables_editor_id_table_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique('tables_editor_id_table_number_unique');
        });
    }
};
