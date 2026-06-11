<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-venue presentation settings. Defaults ('$' + Spanish guest pages)
 * mirror the app's previous hard-coded behaviour so existing tenants see
 * no change until they pick their own values in Settings. [F-9, F-10]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency_symbol', 8)->default('$')->after('editor_metadata');
            $table->string('locale', 5)->default('es')->after('currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['currency_symbol', 'locale']);
        });
    }
};
