<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client invoice details captured per table session — the real version
 * of the decorative form removed in the trust-layer cleanup [#6].
 * Routine in LatAm/EU venues where guests ask for a tax invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_session_id')->index();
            $table->unsignedBigInteger('table_id')->index();
            $table->unsignedBigInteger('editor_id')->index();
            $table->string('name');
            $table->string('tax_id', 64);
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invoices');
    }
};
