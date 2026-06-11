<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue timezone + business-day cutoff [F-22]: a bar's "day" doesn't end
 * at UTC midnight. Analytics bucket on the venue's business day (cutoff
 * hour, e.g. 06:00 local). Defaults (UTC, cutoff 0) preserve previous
 * behaviour until the venue configures them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_timezone', 64)->default('UTC')->after('locale');
            $table->unsignedTinyInteger('day_cutoff_hour')->default(0)->after('business_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['business_timezone', 'day_cutoff_hour']);
        });
    }
};
