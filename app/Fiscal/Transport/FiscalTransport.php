<?php

namespace App\Fiscal\Transport;

use App\Models\FiscalDocument;
use App\Models\User;

/**
 * Takes an UNSIGNED factura XML through signing (XAdES-BES) and
 * transmission to the SRI, synchronously — since 1 January 2026 SRI
 * requires real-time transmission, so there is no nightly batch.
 *
 * Implementations: a gateway (Dátil, Factuplan, …) that holds the
 * venue's .p12 and speaks SOAP to SRI, or a self-hosted signer. The
 * ledger persists whatever the driver reports on the document.
 */
interface FiscalTransport
{
    /**
     * Sign and submit. Must set on $document (and save): status, xml_signed
     * when available, xml_authorized + authorization_number + authorized_at
     * on success, error_code + error_message + sri_response on rejection.
     * Throw only for infrastructure failures (network, misconfiguration).
     */
    public function submit(FiscalDocument $document, User $venue): FiscalDocument;

    /**
     * Ask the authority (or gateway) for the current state of a document
     * that was sent but not yet final (SRI's SLA is up to 24h).
     */
    public function refresh(FiscalDocument $document, User $venue): FiscalDocument;

    public function key(): string;
}
