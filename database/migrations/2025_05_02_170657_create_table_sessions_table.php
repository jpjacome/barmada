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
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_id');
            $table->unsignedInteger('session_number');
            $table->date('date');
            $table->string('unique_token')->unique();
            $table->enum('status', ['open', 'closed', 'reopened']);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->unsignedBigInteger('editor_id');
            $table->timestamps();

            $table->foreign('table_id')->references('id')->on('tables')->onDelete('cascade');
            $table->foreign('opened_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
