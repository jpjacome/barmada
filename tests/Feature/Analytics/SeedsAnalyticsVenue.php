<?php

namespace Tests\Feature\Analytics;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Carbon\Carbon;

/**
 * A fully deterministic venue for analytics tests.
 *
 * No faker, no rand(): every product, table, order, item, session,
 * activity log and fiscal document below is a pure function of its
 * index, so the numbers the analytics read models produce are stable
 * across runs and machines. The clock is pinned by the caller
 * (2026-09-08 20:00 America/Guayaquil) and the venue keeps a cutoff of
 * 06:00, so the fixtures deliberately straddle business-day boundaries
 * (the 01:00 orders belong to the *previous* business day).
 */
trait SeedsAnalyticsVenue
{
    protected string $venueTimezone = 'America/Guayaquil';

    protected int $venueCutoff = 6;

    /** @var array<int, Product> */
    protected array $seedProducts = [];

    /** @var array<int, Table> */
    protected array $seedTables = [];

    /** @var array<int, User> */
    protected array $seedStaff = [];

    /** The instant every analytics range in these tests is measured from. */
    protected function analyticsNow(): Carbon
    {
        return Carbon::parse('2026-09-08 20:00:00', $this->venueTimezone)->utc();
    }

    protected function seedAnalyticsVenue(int $days = 40, int $ordersPerDay = 5): User
    {
        $venue = User::factory()->create([
            'username' => 'la_bodeguita',
            'name' => 'La Bodeguita',
        ]);
        $venue->forceFill([
            'is_editor' => true,
            'editor_id' => $venue->id,
            'business_timezone' => $this->venueTimezone,
            'day_cutoff_hour' => $this->venueCutoff,
            'default_tax_code' => '4',
            'prices_include_tax' => true,
            'service_charge_enabled' => true,
            'service_charge_rate_bp' => 1000,
        ])->save();
        $venue->refresh();

        foreach (['Ana Torres', 'Beto Ruiz'] as $i => $name) {
            $staff = User::factory()->create(['username' => 'staff'.$i, 'name' => $name]);
            $staff->forceFill(['is_staff' => true, 'editor_id' => $venue->id])->save();
            $this->seedStaff[] = $staff->refresh();
        }

        $categories = [];
        foreach (['Cervezas', 'Cocteles', 'Comida'] as $name) {
            $categories[$name] = Category::create([
                'name' => $name,
                'editor_id' => $venue->id,
            ]);
        }

        // name, price (gross, IVA-inclusive), category, tax code
        $catalog = [
            ['Pilsener', 2.50, 'Cervezas', '4'],
            ['Club Verde', 3.00, 'Cervezas', '4'],
            ['Mojito', 6.50, 'Cocteles', '4'],
            ['Caipirinha', 7.00, 'Cocteles', '4'],
            ['Ceviche', 9.00, 'Comida', '4'],
            ['Patacones', 4.00, 'Comida', '0'],
        ];
        foreach ($catalog as [$name, $price, $category, $taxCode]) {
            $this->seedProducts[] = Product::create([
                'name' => $name,
                'price' => $price,
                'tax_code' => $taxCode,
                'category_id' => $categories[$category]->id,
                'editor_id' => $venue->id,
                'is_available' => true,
            ]);
        }

        for ($n = 1; $n <= 5; $n++) {
            $this->seedTables[] = Table::create([
                'editor_id' => $venue->id,
                'table_number' => $n,
                'status' => 'open',
                'unique_token' => sprintf('token-%08d', $n),
            ]);
        }

        // Local hour of each of the day's orders. 01:00 lands before the
        // 06:00 cutoff, so it belongs to the previous business day.
        $hours = [12, 14, 17, 19, 1];
        $statuses = ['pending', 'delivered', 'paid'];
        $methods = ['cash', 'card', 'transfer', null];
        $idx = 0;

        for ($daysAgo = $days - 1; $daysAgo >= 0; $daysAgo--) {
            $localDay = Carbon::parse('2026-09-08 00:00:00', $this->venueTimezone)->subDays($daysAgo);

            for ($j = 0; $j < $ordersPerDay; $j++) {
                $idx++;
                $localAt = $localDay->copy()
                    ->setTime($hours[$j % count($hours)], ($j * 17 + $daysAgo * 7) % 60);

                // Today's late slots have not happened yet at 20:00.
                if ($localAt->greaterThanOrEqualTo(Carbon::parse('2026-09-08 20:00:00', $this->venueTimezone))) {
                    continue;
                }

                $cancelled = $idx % 9 === 0;
                $createdBy = match ($idx % 4) {
                    0 => $this->seedStaff[0]->id,
                    1 => $this->seedStaff[1]->id,
                    default => null,
                };

                $order = Order::create([
                    'editor_id' => $venue->id,
                    'table_id' => $this->seedTables[$idx % 5]->id,
                    'status' => $cancelled ? 'cancelled' : $statuses[$idx % 3],
                    'created_by' => $createdBy,
                    'total_amount' => 0,
                    'amount_paid' => 0,
                    'amount_left' => 0,
                ]);
                $order->created_at = $localAt->copy()->utc();
                $order->updated_at = $order->created_at;
                $order->saveQuietly();

                $gross = 0.0;
                $net = 0.0;
                $tax = 0.0;
                $itemCount = 1 + ($idx % 3);

                for ($k = 0; $k < $itemCount; $k++) {
                    $product = $this->seedProducts[($idx * 3 + $k) % 6];
                    $quantity = 1 + (($idx + $k) % 2);
                    $rateBp = $product->tax_code === '0' ? 0 : 1500;
                    $unitNet = round((float) $product->price / (1 + $rateBp / 10000), 2);
                    $unitTax = round((float) $product->price - $unitNet, 2);
                    $paid = ($idx + $k) % 3 !== 0;

                    $item = new OrderItem([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $product->price,
                        'net_price' => $unitNet,
                        'tax_amount' => $unitTax,
                        'tax_code' => $product->tax_code,
                        'tax_rate_bp' => $rateBp,
                        'is_paid' => $paid,
                        'paid_at' => $paid ? $order->created_at->copy()->addMinutes(30) : null,
                        'paid_by' => $paid ? $this->seedStaff[($idx + $k) % 2]->id : null,
                        'payment_method' => $paid ? $methods[($idx + $k) % 4] : null,
                        'item_index' => $k,
                    ]);
                    // Cost of goods is snapshotted by the stock ledger, not
                    // mass-assigned — set it the same way it does.
                    $item->cost_amount = round((float) $product->price * 0.35, 4);
                    $item->save();

                    $gross += (float) $product->price * $quantity;
                    $net += $unitNet * $quantity;
                    $tax += $unitTax * $quantity;
                }

                $serviceCharge = $idx % 2 === 0 ? round($net * 0.10, 2) : 0.0;
                $order->forceFill([
                    'total_amount' => round($gross, 2),
                    'subtotal' => round($net, 2),
                    'tax_total' => round($tax, 2),
                    'service_charge_rate_bp' => $idx % 2 === 0 ? 1000 : 0,
                    'service_charge' => $serviceCharge,
                    'grand_total' => round($gross + $serviceCharge, 2),
                ])->saveQuietly();
            }

            // Three sessions a day on three distinct tables.
            for ($m = 0; $m < 3; $m++) {
                $table = $this->seedTables[($daysAgo + $m) % 5];
                $opened = $localDay->copy()->setTime(12 + $m * 3, ($daysAgo * 11 + $m * 5) % 60);
                if ($opened->greaterThanOrEqualTo(Carbon::parse('2026-09-08 20:00:00', $this->venueTimezone))) {
                    continue;
                }
                $duration = 45 + (($daysAgo * 13 + $m * 29) % 90);

                TableSession::create([
                    'table_id' => $table->id,
                    'editor_id' => $venue->id,
                    'session_number' => $m + 1,
                    'date' => $localDay->toDateString(),
                    'unique_token' => sprintf('sess-%03d-%d', $daysAgo, $m),
                    'status' => ($daysAgo + $m) % 7 === 0 ? 'reopened' : 'closed',
                    'opened_at' => $opened->copy()->utc(),
                    'closed_at' => $opened->copy()->addMinutes($duration)->utc(),
                    'opened_by' => $this->seedStaff[0]->id,
                    'closed_by' => $this->seedStaff[1]->id,
                ]);
            }

            // QR scans and payment logs.
            foreach ([[12, 30, 0], [18, 30, 2]] as [$h, $min, $offset]) {
                $at = $localDay->copy()->setTime($h, $min);
                if ($at->greaterThanOrEqualTo(Carbon::parse('2026-09-08 20:00:00', $this->venueTimezone))) {
                    continue;
                }
                $log = ActivityLog::create([
                    'editor_id' => $venue->id,
                    'type' => 'qr_scan',
                    'table_id' => $this->seedTables[($daysAgo + $offset) % 5]->id,
                    'description' => 'QR scanned',
                ]);
                $log->created_at = $at->copy()->utc();
                $log->updated_at = $log->created_at;
                $log->saveQuietly();
            }

            foreach ([[0, 13], [1, 18]] as [$p, $hour]) {
                $at = $localDay->copy()->setTime($hour, 15 + $p);
                if ($at->greaterThanOrEqualTo(Carbon::parse('2026-09-08 20:00:00', $this->venueTimezone))) {
                    continue;
                }
                $log = ActivityLog::create([
                    'editor_id' => $venue->id,
                    'type' => 'payment',
                    'table_id' => $this->seedTables[($daysAgo + $p) % 5]->id,
                    'user_id' => ($daysAgo + $p) % 3 === 0 ? null : $this->seedStaff[$p]->id,
                    'amount' => 10 + $daysAgo + $p,
                    'description' => 'Payment recorded',
                ]);
                $log->created_at = $at->copy()->utc();
                $log->updated_at = $log->created_at;
                $log->saveQuietly();
            }
        }

        $this->seedFiscalDocuments($venue);

        return $venue;
    }

    /** A handful of documents across the last three business months. */
    protected function seedFiscalDocuments(User $venue): void
    {
        $statuses = ['authorized', 'built', 'draft', 'authorized', 'rejected', 'built'];
        foreach ($statuses as $i => $status) {
            $localAt = Carbon::parse('2026-09-08 12:00:00', $this->venueTimezone)->subDays($i * 20);
            $doc = FiscalDocument::create([
                'editor_id' => $venue->id,
                'doc_type' => '01',
                'ambiente' => 1,
                'estab' => '001',
                'pto_emi' => '001',
                'secuencial' => $i + 1,
                'clave_acceso' => str_pad((string) ($i + 1), 49, '0', STR_PAD_LEFT),
                'fecha_emision' => $localAt->toDateString(),
                'buyer_id_type' => '07',
                'buyer_identification' => '9999999999999',
                'buyer_name' => 'CONSUMIDOR FINAL',
                'subtotal' => 100 + $i,
                'tax_total' => 15,
                'importe_total' => 115 + $i,
                'taxes' => [],
                'lines' => [],
                'payments' => [],
                'status' => $status,
            ]);
            $doc->created_at = $localAt->copy()->utc();
            $doc->updated_at = $doc->created_at;
            $doc->saveQuietly();
        }
    }
}
