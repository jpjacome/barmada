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
        // Drop foreign keys first
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });

        // Make columns non-nullable
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable(false)->change();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable(false)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable(false)->change();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable(false)->change();
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable(false)->change();
        });

        // Re-add foreign keys with RESTRICT
        Schema::table('tables', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('restrict');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('restrict');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('restrict');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('restrict');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new foreign keys
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['editor_id']);
        });

        // Make columns nullable again
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->change();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->change();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->change();
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('editor_id')->nullable()->change();
        });

        // Re-add original foreign keys with SET NULL
        Schema::table('tables', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
