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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('editor_metadata');
            $table->string('business_name')->nullable()->after('is_editor');
            $table->string('contact_phone')->nullable()->after('business_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('editor_metadata')->nullable()->after('is_editor');
            $table->dropColumn(['business_name', 'contact_phone', 'contact_email']);
        });
    }
};
