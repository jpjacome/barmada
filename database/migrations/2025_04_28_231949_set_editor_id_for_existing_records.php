<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set editor_id = 2 for all existing records in relevant tables
        DB::table('tables')->update(['editor_id' => 2]);
        DB::table('products')->update(['editor_id' => 2]);
        DB::table('orders')->update(['editor_id' => 2]);
        DB::table('categories')->update(['editor_id' => 2]);
        DB::table('activity_logs')->update(['editor_id' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally set editor_id back to null for rollback
        DB::table('tables')->update(['editor_id' => null]);
        DB::table('products')->update(['editor_id' => null]);
        DB::table('orders')->update(['editor_id' => null]);
        DB::table('categories')->update(['editor_id' => null]);
        DB::table('activity_logs')->update(['editor_id' => null]);
    }
};
