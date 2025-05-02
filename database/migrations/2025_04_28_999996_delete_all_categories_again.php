<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $count = DB::table('categories')->count();
        DB::table('categories')->delete();
        Log::info("[MIGRATION] Deleted all categories (count: {$count})");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback action needed for this migration
    }
};