<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('icon_type')->default('bootstrap')->after('price'); // 'bootstrap' or 'svg'
            $table->text('icon_value')->nullable()->after('icon_type'); // Bootstrap icon name or SVG content
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['icon_type', 'icon_value']);
        });
    }
};
