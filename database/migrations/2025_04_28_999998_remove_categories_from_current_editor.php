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
        // Get the current editor's user ID (set this manually or via env/config if needed)
        $currentEditorId = env('CURRENT_EDITOR_ID');
        if ($currentEditorId) {
            $affected = DB::table('categories')
                ->where('editor_id', $currentEditorId)
                ->update(['editor_id' => null]);
            Log::info("[MIGRATION] Set editor_id to null for {$affected} categories belonging to editor_id={$currentEditorId}");
        } else {
            Log::warning('[MIGRATION] CURRENT_EDITOR_ID env variable not set. No categories updated.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration (cannot restore previous editor_id without backup)
    }
};
