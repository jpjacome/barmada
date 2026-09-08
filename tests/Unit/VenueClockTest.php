<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\VenueClock;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class VenueClockTest extends TestCase
{
    private function venue(string $tz): User
    {
        $venue = new User();
        $venue->is_editor = true;
        $venue->business_timezone = $tz;

        return $venue;
    }

    public function test_it_converts_utc_instants_to_the_venues_wall_clock(): void
    {
        // 02:40 UTC on the 8th is 21:40 on the 7th in Quito — the bill used
        // to print the former.
        $moment = Carbon::parse('2026-09-08 02:40:00', 'UTC');

        $this->assertSame('21:40', VenueClock::format($this->venue('America/Guayaquil'), $moment));
        $this->assertSame('2026-09-07', VenueClock::at($this->venue('America/Guayaquil'), $moment)->toDateString());
    }

    public function test_it_accepts_database_strings(): void
    {
        $this->assertSame('21:40:00', VenueClock::format($this->venue('America/Guayaquil'), '2026-09-08 02:40:00', 'H:i:s'));
    }

    public function test_a_venue_without_a_timezone_stays_in_utc(): void
    {
        $venue = $this->venue('');
        $this->assertSame('02:40', VenueClock::format($venue, Carbon::parse('2026-09-08 02:40:00', 'UTC')));
        $this->assertSame('02:40', VenueClock::format(null, Carbon::parse('2026-09-08 02:40:00', 'UTC')));
    }

    public function test_null_moments_render_empty(): void
    {
        $this->assertSame('', VenueClock::format($this->venue('America/Guayaquil'), null));
        $this->assertNull(VenueClock::at($this->venue('America/Guayaquil'), ''));
    }

    public function test_venue_for_resolves_editors_to_themselves_and_admins_to_nothing(): void
    {
        $editor = $this->venue('America/Guayaquil');
        $this->assertSame($editor, VenueClock::venueFor($editor));

        $admin = new User();
        $admin->is_admin = true;
        $this->assertNull(VenueClock::venueFor($admin));
        $this->assertNull(VenueClock::venueFor(null));
    }
}
