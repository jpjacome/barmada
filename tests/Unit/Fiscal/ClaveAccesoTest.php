<?php

namespace Tests\Unit\Fiscal;

use App\Fiscal\ClaveAcceso;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ClaveAccesoTest extends TestCase
{
    /**
     * Two genuine production access keys observed on authorised SRI
     * documents: the algorithm must reproduce their check digits.
     */
    public function test_check_digit_matches_real_authorised_keys(): void
    {
        foreach ([
            '0211202401050306179800120010020000000677300995216',
            '1211202401092554321700110021000000000790925543211',
        ] as $clave) {
            $this->assertTrue(ClaveAcceso::isValid($clave), "Real key {$clave} must validate.");
        }
    }

    public function test_the_fichas_worked_example(): void
    {
        // Ficha Técnica p.12: base ending in the chain whose weighted sum is
        // 104 → 104 mod 11 = 5 → 11 − 5 = 6.
        $this->assertSame(6, ClaveAcceso::checkDigit(str_repeat('0', 40).'41261533'));
    }

    public function test_builds_a_49_digit_key_with_zero_padding(): void
    {
        $clave = ClaveAcceso::build(
            Carbon::create(2026, 9, 7, 21, 40, 0, 'America/Guayaquil'),
            '01', '1790012345001', 1, '1', '1', 123, '12345678',
        );

        $this->assertSame(49, strlen($clave));
        $this->assertStringStartsWith('07092026', $clave, 'ddmmyyyy of the emission date');
        $this->assertSame('01', substr($clave, 8, 2));
        $this->assertSame('1790012345001', substr($clave, 10, 13));
        $this->assertSame('1', substr($clave, 23, 1));
        $this->assertSame('001001', substr($clave, 24, 6), 'estab/ptoEmi zero-padded');
        $this->assertSame('000000123', substr($clave, 30, 9), 'secuencial zero-padded to 9');
        $this->assertSame('12345678', substr($clave, 39, 8));
        $this->assertSame('1', substr($clave, 47, 1), 'tipoEmision normal');
        $this->assertTrue(ClaveAcceso::isValid($clave));
    }

    public function test_rejects_malformed_inputs(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ClaveAcceso::build(Carbon::now(), '01', '123', 1, '001', '001', 1, '12345678');
    }

    public function test_a_flipped_digit_invalidates_the_key(): void
    {
        $clave = '0211202401050306179800120010020000000677300995216';
        $tampered = substr_replace($clave, '7', 33, 1);
        $this->assertFalse(ClaveAcceso::isValid($tampered));
    }
}
