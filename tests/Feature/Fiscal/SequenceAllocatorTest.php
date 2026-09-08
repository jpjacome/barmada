<?php

namespace Tests\Feature\Fiscal;

use App\Fiscal\SequenceAllocator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_gapless_and_scoped_per_ambiente_type_estab_and_point(): void
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $alloc = app(SequenceAllocator::class);

        $this->assertSame(1, $alloc->next($venue, 1, '01', '001', '001'));
        $this->assertSame(2, $alloc->next($venue, 1, '01', '001', '001'));
        $this->assertSame(3, $alloc->next($venue, 1, '01', '001', '001'));

        // Different point of emission, doc type or ambiente: own sequence.
        $this->assertSame(1, $alloc->next($venue, 1, '01', '001', '002'));
        $this->assertSame(1, $alloc->next($venue, 1, '04', '001', '001'));
        $this->assertSame(1, $alloc->next($venue, 2, '01', '001', '001'));
    }

    public function test_sequences_are_per_venue(): void
    {
        $a = User::factory()->create(['is_editor' => true, 'username' => 'a'.uniqid()]);
        $b = User::factory()->create(['is_editor' => true, 'username' => 'b'.uniqid()]);
        $alloc = app(SequenceAllocator::class);

        $alloc->next($a, 1, '01', '001', '001');
        $alloc->next($a, 1, '01', '001', '001');

        $this->assertSame(1, $alloc->next($b, 1, '01', '001', '001'));
    }

    public function test_seeding_continues_from_a_previous_system_and_never_goes_backwards(): void
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $alloc = app(SequenceAllocator::class);

        $alloc->seed($venue, 2, '01', '001', '001', 4520);
        $this->assertSame(4521, $alloc->next($venue, 2, '01', '001', '001'));

        $alloc->seed($venue, 2, '01', '001', '001', 10); // lower: ignored
        $this->assertSame(4522, $alloc->next($venue, 2, '01', '001', '001'));
    }
}
