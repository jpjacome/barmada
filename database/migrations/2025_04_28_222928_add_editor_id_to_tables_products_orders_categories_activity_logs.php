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
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->after('id');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->after('id');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->after('id');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->after('id');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->after('id');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
            $table->dropColumn('editor_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
            $table->dropColumn('editor_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
            $table->dropColumn('editor_id');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
            $table->dropColumn('editor_id');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
            $table->dropColumn('editor_id');
        });
    }
};
