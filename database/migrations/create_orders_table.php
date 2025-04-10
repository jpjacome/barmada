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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Foreign key to tables
            $table->foreignId('table_id')->constrained('tables');
            // Order status
            $table->string('status')->default('pending');
            // Product columns to store quantities with new names
            $table->integer('product1_qty')->default(0);
            $table->integer('product2_qty')->default(0);
            $table->integer('product3_qty')->default(0);
            $table->integer('product4_qty')->default(0);
            $table->integer('product5_qty')->default(0);
            $table->integer('product6_qty')->default(0);
            $table->integer('product7_qty')->default(0);
            $table->integer('product8_qty')->default(0);
            $table->integer('product9_qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}; 