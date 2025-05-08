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
        Schema::create('table_session_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_session_id');
            $table->string('ip_address');
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamps();

            $table->foreign('table_session_id')->references('id')->on('table_sessions')->onDelete('cascade');
            $table->index(['table_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_session_requests');
    }
};
