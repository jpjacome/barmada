<?php

/*
|--------------------------------------------------------------------------
| Fiscal configuration — Ecuador (SRI) first, jurisdiction-agnostic shape
|--------------------------------------------------------------------------
|
| Tax codes follow the SRI "codigoPorcentaje" catalogue for IVA (Ficha
| Técnica de Comprobantes Electrónicos, Tabla 17) so a code stored on an
| order item can be emitted on a factura without translation. Rates are
| in basis points (1500 = 15.00%).
|
| The venue chooses its default code; products may override it (e.g. a
| 0%-rated item); date-bounded overrides (tax_rate_overrides) remap a code
| for a period — the mechanism behind Ecuador's 8% "IVA turístico" days.
*/

return [

    // Catalogue of IVA codes. Keys are SRI codigoPorcentaje values.
    'iva_codes' => [
        '0' => ['rate_bp' => 0,    'label' => 'IVA 0%'],
        '2' => ['rate_bp' => 1200, 'label' => 'IVA 12%'],   // historical
        '3' => ['rate_bp' => 1400, 'label' => 'IVA 14%'],   // historical
        '4' => ['rate_bp' => 1500, 'label' => 'IVA 15%'],   // current general rate
        '5' => ['rate_bp' => 500,  'label' => 'IVA 5%'],
        '6' => ['rate_bp' => 0,    'label' => 'No objeto de IVA'],
        '7' => ['rate_bp' => 0,    'label' => 'Exento de IVA'],
        '8' => ['rate_bp' => 800,  'label' => 'IVA diferenciado 8%'],
        '10' => ['rate_bp' => 1300, 'label' => 'IVA 13%'],
    ],

    // Code applied when neither the product nor the venue says otherwise.
    'default_iva_code' => '4',

    // Whether a brand-new venue's menu prices are treated as IVA-inclusive.
    // Ecuadorian retail menus almost always print the price the guest pays.
    'prices_include_tax_default' => true,

    // The legal "10% de servicio" (Decreto Supremo 1269). Off by default —
    // only first- and second-category tourism establishments must charge
    // it. It is computed on the pre-IVA subtotal, is NOT part of the IVA
    // base, and may not exceed 10%.
    'service_charge' => [
        'enabled_default' => false,
        'rate_bp_default' => 1000,
        'max_rate_bp' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Electronic invoicing (comprobantes electrónicos)
    |--------------------------------------------------------------------------
    */

    // Above this importe total the buyer must be identified (Ficha §9.10 —
    // SRI enforces 50 even though the Reglamento still says 200).
    'consumidor_final_max_total' => 50.00,

    // tipoIdentificacionComprador 07 and its fixed identification.
    'consumidor_final' => [
        'id_type' => '07',
        'identification' => '9999999999999',
        'name' => 'CONSUMIDOR FINAL',
    ],

    // RUC of the software provider, emitted as campoAdicional "RUC Proveedor"
    // (mandatory from 26 Sep 2026 when the software is commercialised;
    // Res. NAC-DGERCGC26-00000027). Leave null for a venue's in-house use.
    'software_provider_ruc' => env('FISCAL_SOFTWARE_PROVIDER_RUC'),

    // Transport drivers: sign + transmit. 'none' parks documents unsigned.
    'providers' => [
        'none' => \App\Fiscal\Transport\NullTransport::class,
    ],

];
