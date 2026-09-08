<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory as a ledger, not a counter.
 *
 *  stock_items         what the venue buys and counts (a bottle of gin, a
 *                      keg, a lime), in its own unit
 *  product_components  the recipe: how much of each stock item one sold
 *                      unit of a product consumes — the layer that lets a
 *                      bar track pours, not just bottles
 *  stock_movements     every change, signed, with who/when/why; on-hand
 *                      and cost are projections of it
 *
 * Depletion happens when an order is DELIVERED (never on order, never for
 * cancelled orders) and each delivered unit records its cost of goods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editor_id')->index();
            $table->string('name', 120);
            $table->string('sku', 40)->nullable();
            // unit | ml | cl | l | g | kg
            $table->string('unit', 8)->default('unit');
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->nullable();
            // Weighted-average cost per unit, in venue currency.
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['editor_id', 'name']);
        });

        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('stock_item_id')->index();
            $table->decimal('quantity_per_unit', 12, 4);
            $table->timestamps();
            $table->unique(['product_id', 'stock_item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editor_id');
            $table->unsignedBigInteger('stock_item_id');
            // purchase | sale | waste | count | adjustment | reversal
            $table->string('type', 16);
            // Signed in the item's unit: positive in, negative out.
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['editor_id', 'stock_item_id', 'occurred_at'], 'stock_movements_item_time_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_movements_reference_idx');
            $table->index(['editor_id', 'type', 'occurred_at'], 'stock_movements_type_time_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Cost of goods for this unit, snapshotted at depletion.
            $table->decimal('cost_amount', 10, 4)->nullable()->after('tax_rate_bp');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('cost_amount'));
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_components');
        Schema::dropIfExists('stock_items');
    }
};
