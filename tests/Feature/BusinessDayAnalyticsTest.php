<?php

namespace Tests\Feature;

use App\Support\BusinessDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Business-day analytics bucketing [F-22]: a venue's "today" follows its
 * own timezone and cutoff hour, so a Friday night's small hours count
 * toward Friday.
 */
class BusinessDayAnalyticsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_defaults_reproduce_utc_calendar_days(): void
    {
        $editor = $this->makeEditor(); // UTC, cutoff 0

        $now = Carbon::parse('2026-06-12 15:00:00', 'UTC');
        [$from, $to] = BusinessDay::rangeUtc($editor, 'today', $now);

        $this->assertSame('2026-06-12 00:00:00', $from->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $from->timezoneName);
        $this->assertTrue($to->equalTo($now));
    }

    public function test_small_hours_belong_to_the_previous_business_day(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['business_timezone' => 'America/Guayaquil', 'day_cutoff_hour' => 6])->save();

        // 03:00 Saturday local (= 08:00 UTC): still Friday's business day.
        $now = Carbon::parse('2026-06-13 08:00:00', 'UTC'); // 03:00 local
        [$from] = BusinessDay::rangeUtc($editor->fresh(), 'today', $now);

        // Friday's business day started Friday 06:00 local = 11:00 UTC.
        $this->assertSame('2026-06-12 11:00:00', $from->format('Y-m-d H:i:s'));
    }

    public function test_after_cutoff_a_new_business_day_starts(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['business_timezone' => 'America/Guayaquil', 'day_cutoff_hour' => 6])->save();

        // 10:00 Saturday local (= 15:00 UTC): Saturday's business day.
        $now = Carbon::parse('2026-06-13 15:00:00', 'UTC');
        [$from] = BusinessDay::rangeUtc($editor->fresh(), 'today', $now);

        $this->assertSame('2026-06-13 11:00:00', $from->format('Y-m-d H:i:s')); // Sat 06:00 local
    }

    public function test_peak_hours_read_in_the_venues_clock(): void
    {
        $editor = $this->makeEditor();
        $editor->forceFill(['business_timezone' => 'America/Guayaquil'])->save();

        $utc = Carbon::parse('2026-06-13 02:00:00', 'UTC'); // 21:00 local
        $this->assertSame('21:00', BusinessDay::localHour($editor->fresh(), $utc));
    }

    public function test_qr_scans_are_now_logged(): void
    {
        $editor = $this->makeEditor();
        $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 5]);

        $this->get('/qr-entry/'.rawurlencode($editor->username).'/5')->assertOk();

        $this->assertSame(1, \App\Models\ActivityLog::acrossEditors()
            ->where('type', 'qr_scan')
            ->where('editor_id', $editor->id)
            ->count());
    }
}
