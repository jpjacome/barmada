<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device (session) requests need to remember which TABLE they were made
 * for, not only which session. Requests recorded while a table is still
 * closed have no session yet — the table id is what lets staff approval
 * adopt them into the session created when the table opens. [F-1]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_session_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('table_id')->nullable()->after('table_session_id')->index();
        });

        // Pre-session requests have no session yet by definition.
        Schema::table('table_session_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('table_session_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('table_session_requests', function (Blueprint $table) {
            $table->dropIndex(['table_id']);
            $table->dropColumn('table_id');
        });

        Schema::table('table_session_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('table_session_id')->nullable(false)->change();
        });
    }
};
