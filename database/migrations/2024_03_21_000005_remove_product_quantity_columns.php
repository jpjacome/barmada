<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $columnsToDrop = [
            'product1_qty', 'product2_qty', 'product3_qty', 'product4_qty', 'product5_qty',
            'product6_qty', 'product7_qty', 'product8_qty', 'product9_qty'
        ];

        Schema::table('orders', function (Blueprint $table) use ($columnsToDrop) {
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('product1_qty')->default(0);
            $table->integer('product2_qty')->default(0);
            $table->integer('product3_qty')->default(0);
            $table->integer('product4_qty')->default(0);
            $table->integer('product5_qty')->default(0);
            $table->integer('product6_qty')->default(0);
            $table->integer('product7_qty')->default(0);
            $table->integer('product8_qty')->default(0);
            $table->integer('product9_qty')->default(0);
        });
    }
}; 