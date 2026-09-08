# Fiscal ledger — comprobantes electrónicos (Ecuador / SRI)

Barmada issues **facturas electrónicas** for a table session from the venue's
own numbering, in the SRI offline scheme (Ficha Técnica v2.34). This document
explains the model and how signing/transmission plugs in.

## What lives where

| Piece | Where | Role |
|---|---|---|
| Tax snapshot per unit | `order_items.net_price / tax_amount / tax_code / tax_rate_bp` | Money the XML will use; never recomputed |
| Order totals | `orders.subtotal / tax_total / service_charge / grand_total` | `RecalculateOrderTotals` |
| Venue fiscal identity | `users.fiscal_*` | RUC, razón social, dirección, estab/ptoEmi, régimen, ambiente, provider |
| Gapless numbering | `fiscal_sequences` + `App\Fiscal\SequenceAllocator` | One counter per (venue, ambiente, doc type, estab, ptoEmi), row-locked |
| Documents | `fiscal_documents` + `App\Models\FiscalDocument` | Immutable snapshot + transport lifecycle |
| Access key | `App\Fiscal\ClaveAcceso` | 49 digits, módulo 11 check digit |
| XML | `App\Fiscal\FacturaXmlBuilder` | factura **v1.1.0**, unsigned |
| Issue flow | `App\Actions\Fiscal\IssueFactura` | Validates, numbers, builds, submits |
| Transport | `App\Fiscal\Transport\*` | Sign + transmit; `none` parks documents |
| Surfaces | bill page button, `/fiscal/{doc}/ride`, `/fiscal/{doc}/xml`, `/api/v1/tables/{t}/factura`, `/api/v1/fiscal-documents` | |

## Lifecycle

```
draft ─► built ─► signed ─► sent ─► authorized
                                └─► rejected   (keep the number, fix, resend)
                     └────────────► error      (infrastructure; retry)
```

With `fiscal_provider = none` a document stops at **built**: numbered, XML
stored, nothing leaves the server. When a gateway is configured later the
parked documents are submitted **in order** — their secuenciales are already
allocated and SRI requires them gapless.

## Rules encoded

- **IVA** codes follow SRI Tabla 17 (`4` = 15% today). Rate is per unit, at
  order time, with date-bounded overrides for the 8% *IVA turístico* days.
- **Propina / 10% servicio** (Decreto Supremo 1269): opt-in per venue,
  computed on the pre-IVA subtotal, capped at 10%, **not** in the IVA base,
  emitted in `<propina>` (which the Ficha marks *Obligatorio* — `0.00` when
  unused) and included in `importeTotal` and in `pagos`.
- **Consumidor final** (`07 / 9999999999999`) only up to **USD 50** importe
  total (Ficha §9.10); above that the captured `ClientInvoice` must carry an
  identification (13 digits → RUC `04`, 10 → cédula `05`, else pasaporte `06`).
- **Forma de pago** from item `payment_method`: cash → `01`, card → `19`,
  transfer/other → `20`. Unpaid or unspecified units count as cash. The
  propina rides with the largest bucket so `pagos` sum to `importeTotal`.
- **XML hygiene**: xsd:sequence order, no empty optionals, whitespace
  collapsed (pattern `[^\n]*`), `&` escaped by the serializer, two decimals on
  money, six on `cantidad`/`precioUnitario`.
- **`campoAdicional "RUC Proveedor"`** is emitted when
  `FISCAL_SOFTWARE_PROVIDER_RUC` is set (mandatory from 26 Sep 2026 when the
  software is commercialised — Res. NAC-DGERCGC26-00000027).
- **Retention**: keep `xml_authorized` (SRI's copy) and the RIDE for 7 years
  (Res. 233 art. 11). SRI's own store is not the venue's archive.

## Adding a transport (gateway or self-hosted signer)

1. Implement `App\Fiscal\Transport\FiscalTransport`:
   - `submit()` — sign the `xml_unsigned` (XAdES-BES, **RSA-SHA1**, ETSI
     1.3.2, enveloped, inclusive C14N) and send to recepción; poll
     autorización; persist `status`, `xml_signed`, `xml_authorized`,
     `authorization_number`, `authorized_at`, `sri_response`,
     `error_code/message`.
   - `refresh()` — re-query a document stuck in `sent` (SRI SLA up to 24 h).
2. Register it in `config/fiscal.php` → `providers['datil'] = DatilTransport::class`.
3. The venue picks it in Settings → Fiscal profile. Nothing else changes.

Do **not** hand-roll the XAdES-BES layer unless you have to: the reference
projects that died, died there. If self-hosting is mandatory, evaluate
`matiz-studio-creative/sri-toolkit` (only PHP package on Ficha v2.34 with
RIDE) and add `dj-Andres/signSri`'s validator as a pre-flight.

## Not yet covered

- Nota de crédito (`04`) for corrections/voids after authorisation.
- Emailing XML + RIDE to the buyer (Res. 233 art. 6 requires **both**).
- Liquidación de compra (`03`) for purchases from unregistered suppliers —
  belongs with inventory.
- Retenciones — a small bar is not an agente de retención by default.
