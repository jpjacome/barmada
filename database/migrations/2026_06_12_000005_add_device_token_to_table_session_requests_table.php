<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device approvals keyed on a browser cookie instead of the IP address
 * alone [F-18]: venue NAT can put every guest behind one IP, and mobile
 * CGNAT rotates a guest's IP mid-session. IP remains as a fallback for
 * requests recorded before this change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_session_requests', function (Blueprint $table) {
            $table->string('device_token', 64)->nullable()->after('ip_address')->index();
        });
    }

    public function down(): void
    {
        Schema::table('table_session_requests', function (Blueprint $table) {
            $table->dropIndex(['device_token']);
            $table->dropColumn('device_token');
        });
    }
};
