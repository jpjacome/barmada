<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the first editor
        $editor = DB::table('users')->where('is_editor', true)->first();
        if ($editor) {
            // Update all categories with null editor_id
            $affected = DB::table('categories')
                ->whereNull('editor_id')
                ->update(['editor_id' => $editor->id]);
            Log::info("[MIGRATION] Assigned editor_id={$editor->id} to {$affected} categories with null editor_id");
        } else {
            Log::warning('[MIGRATION] No editor found to assign to categories with null editor_id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally, set back to null (not strictly necessary)
        // DB::table('categories')->whereNotNull('editor_id')->update(['editor_id' => null]);
    }
};
