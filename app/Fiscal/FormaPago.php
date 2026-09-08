<?php

namespace App\Fiscal;

/**
 * SRI Tabla 24 — the only eight forma de pago codes that exist.
 */
final class FormaPago
{
    public const SIN_SISTEMA_FINANCIERO = '01';   // cash
    public const COMPENSACION = '15';
    public const TARJETA_DEBITO = '16';
    public const DINERO_ELECTRONICO = '17';
    public const TARJETA_PREPAGO = '18';
    public const TARJETA_CREDITO = '19';
    public const OTROS_SISTEMA_FINANCIERO = '20'; // transfers
    public const ENDOSO_TITULOS = '21';

    /**
     * Map Barmada's payment_method to an SRI code. Barmada does not
     * distinguish debit from credit cards; credit is the conservative
     * default and can be refined when the payment UI asks.
     */
    public static function fromPaymentMethod(?string $method): string
    {
        return match ($method) {
            'card' => self::TARJETA_CREDITO,
            'transfer' => self::OTROS_SISTEMA_FINANCIERO,
            'other' => self::OTROS_SISTEMA_FINANCIERO,
            default => self::SIN_SISTEMA_FINANCIERO, // cash / unspecified
        };
    }
}
