<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guest "request bill" / "call waiter" signals, surfaced on the staff board. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_id')->index();
            $table->unsignedBigInteger('table_session_id')->index();
            $table->unsignedBigInteger('editor_id')->index();
            $table->string('type', 20); // bill | waiter
            $table->string('status', 20)->default('pending'); // pending | done
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
