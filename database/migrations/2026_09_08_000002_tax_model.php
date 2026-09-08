<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tax model. There was none: prices were whatever the venue typed,
 * implicitly IVA-inclusive, with no breakdown anywhere. A legal receipt
 * in Ecuador needs a subtotal, the IVA per rate, the optional 10%
 * servicio and the total — and a fiscal document needs the SRI code
 * behind each line. Everything here is a snapshot taken at order time so
 * history survives rate changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('default_tax_code', 4)->default(config('fiscal.default_iva_code', '4'))->after('day_cutoff_hour');
            $table->boolean('prices_include_tax')->default(true)->after('default_tax_code');
            $table->boolean('service_charge_enabled')->default(false)->after('prices_include_tax');
            $table->unsignedSmallInteger('service_charge_rate_bp')->default(1000)->after('service_charge_enabled');
        });

        Schema::table('products', function (Blueprint $table) {
            // NULL = use the venue default.
            $table->string('tax_code', 4)->nullable()->after('price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // price remains the gross amount owed per unit.
            $table->decimal('net_price', 8, 2)->nullable()->after('price');
            $table->decimal('tax_amount', 8, 2)->nullable()->after('net_price');
            $table->string('tax_code', 4)->nullable()->after('tax_amount');
            $table->unsignedSmallInteger('tax_rate_bp')->nullable()->after('tax_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            // total_amount keeps its meaning (gross consumption); these
            // decompose and extend it.
            $table->decimal('subtotal', 10, 2)->nullable()->after('total_amount');
            $table->decimal('tax_total', 10, 2)->nullable()->after('subtotal');
            $table->unsignedSmallInteger('service_charge_rate_bp')->default(0)->after('tax_total');
            $table->decimal('service_charge', 10, 2)->default(0)->after('service_charge_rate_bp');
            $table->decimal('grand_total', 10, 2)->nullable()->after('service_charge');
        });

        Schema::create('tax_rate_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editor_id')->nullable()->index(); // NULL = platform-wide
            $table->string('from_code', 4);
            $table->string('to_code', 4);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['from_code', 'starts_on', 'ends_on'], 'tax_overrides_lookup_idx');
        });

        // Backfill history: every existing item was sold at an implicitly
        // IVA-inclusive price under the venue's (default) code. Compute the
        // split so past bills and future reports carry a breakdown too.
        $defaultCode = (string) config('fiscal.default_iva_code', '4');
        $rateBp = (int) (config("fiscal.iva_codes.{$defaultCode}.rate_bp") ?? 0);

        DB::table('order_items')->whereNull('tax_code')->orderBy('id')->chunkById(500, function ($items) use ($defaultCode, $rateBp) {
            foreach ($items as $item) {
                $grossCents = (int) round(((float) $item->price) * 100);
                $netCents = $rateBp > 0 ? (int) round($grossCents / (1 + $rateBp / 10000)) : $grossCents;
                DB::table('order_items')->where('id', $item->id)->update([
                    'net_price' => $netCents / 100,
                    'tax_amount' => ($grossCents - $netCents) / 100,
                    'tax_code' => $defaultCode,
                    'tax_rate_bp' => $rateBp,
                ]);
            }
        });

        DB::table('orders')->whereNull('subtotal')->orderBy('id')->chunkById(500, function ($orders) {
            foreach ($orders as $order) {
                $sums = DB::table('order_items')->where('order_id', $order->id)
                    ->selectRaw('COALESCE(SUM(net_price),0) as net, COALESCE(SUM(tax_amount),0) as tax, COALESCE(SUM(price),0) as gross')
                    ->first();
                DB::table('orders')->where('id', $order->id)->update([
                    'subtotal' => round((float) $sums->net, 2),
                    'tax_total' => round((float) $sums->tax, 2),
                    'grand_total' => round((float) $sums->gross, 2),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_overrides');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['subtotal', 'tax_total', 'service_charge_rate_bp', 'service_charge', 'grand_total']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['net_price', 'tax_amount', 'tax_code', 'tax_rate_bp']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('tax_code'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['default_tax_code', 'prices_include_tax', 'service_charge_enabled', 'service_charge_rate_bp']));
    }
};
