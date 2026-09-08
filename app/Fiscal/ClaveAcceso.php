<?php

namespace App\Fiscal;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The 49-digit SRI access key (Ficha Técnica, Tabla 1):
 *
 *   fechaEmision ddmmyyyy (8) · codDoc (2) · RUC (13) · ambiente (1) ·
 *   serie estab+ptoEmi (6) · secuencial (9) · código numérico (8) ·
 *   tipoEmision (1) · dígito verificador (1)
 *
 * In the offline scheme this key IS the authorisation number. The check
 * digit is módulo 11 with weights 2..7 cycling from the rightmost digit;
 * a result of 11 becomes 0 and 10 becomes 1.
 */
final class ClaveAcceso
{
    public static function build(
        CarbonInterface $fechaEmision,
        string $codDoc,
        string $ruc,
        int $ambiente,
        string $estab,
        string $ptoEmi,
        int $secuencial,
        string $codigoNumerico,
        int $tipoEmision = 1,
    ): string {
        if (! preg_match('/^\d{13}$/', $ruc)) {
            throw new InvalidArgumentException('RUC must be 13 digits.');
        }
        if (! preg_match('/^\d{8}$/', $codigoNumerico)) {
            throw new InvalidArgumentException('Código numérico must be 8 digits.');
        }

        $base = $fechaEmision->format('dmY')
            .str_pad($codDoc, 2, '0', STR_PAD_LEFT)
            .$ruc
            .$ambiente
            .str_pad($estab, 3, '0', STR_PAD_LEFT)
            .str_pad($ptoEmi, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $secuencial, 9, '0', STR_PAD_LEFT)
            .$codigoNumerico
            .$tipoEmision;

        if (strlen($base) !== 48) {
            throw new InvalidArgumentException('Access key base must be 48 digits, got '.strlen($base).'.');
        }

        return $base.self::checkDigit($base);
    }

    /**
     * Módulo 11, "factor de chequeo ponderado 2": weights 2,3,4,5,6,7
     * cycling from the rightmost digit.
     */
    public static function checkDigit(string $base48): int
    {
        if (! preg_match('/^\d{48}$/', $base48)) {
            throw new InvalidArgumentException('Exactly 48 digits required.');
        }

        $weights = [2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = 47, $j = 0; $i >= 0; $i--, $j++) {
            $sum += ((int) $base48[$i]) * $weights[$j % 6];
        }

        $dv = 11 - ($sum % 11);

        return match ($dv) {
            11 => 0,
            10 => 1,
            default => $dv,
        };
    }

    public static function isValid(string $clave): bool
    {
        return preg_match('/^\d{49}$/', $clave) === 1
            && self::checkDigit(substr($clave, 0, 48)) === (int) $clave[48];
    }

    /**
     * An 8-digit numeric code "a potestad absoluta del emisor". Random is
     * fine; what matters is that the full key is unique, and the
     * secuencial already guarantees that.
     */
    public static function randomCodigoNumerico(): string
    {
        return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
