<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Make first_name and last_name nullable if they exist
            if (Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->change();
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->change();
            }
            // Make name nullable if it exists
            if (Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->change();
            }
            // Add business_name if it doesn't exist
            if (!Schema::hasColumn('users', 'business_name')) {
                $table->string('business_name')->nullable();
            }
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Optionally revert business_name addition
            if (Schema::hasColumn('users', 'business_name')) {
                $table->dropColumn('business_name');
            }
            // Optionally revert nullable change (set NOT NULL, may fail if data exists)
            // $table->string('first_name')->nullable(false)->change();
            // $table->string('last_name')->nullable(false)->change();
            // $table->string('name')->nullable(false)->change();
        });
    }
};
