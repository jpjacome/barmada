<?php

namespace App\Fiscal;

use App\Models\FiscalDocument;
use App\Models\User;
use DOMDocument;
use DOMElement;

/**
 * Builds the SRI factura XML, version 1.1.0 (Ficha Técnica v2.34, Anexo 3).
 *
 * Why 1.1.0: it is the version that allows six decimals on cantidad and
 * precioUnitario; 2.x adds blocks only for third-party rubros and
 * guía-de-remisión substitutes, and 2.0.0 regresses the decimals.
 *
 * Rules that cause most SRI rejections and are enforced here:
 *  - every block is an xsd:sequence — element order matters;
 *  - optional elements are omitted, never emitted empty (minLength=1);
 *  - no newlines inside text nodes (pattern [^\n]*), '&' must be escaped —
 *    DOMDocument does the escaping, we collapse the whitespace;
 *  - amounts have exactly two decimals, cantidad/precioUnitario six;
 *  - <propina> is emitted even when 0.00 (the Ficha marks it Obligatorio).
 *
 * The output is UNSIGNED. Signing (XAdES-BES, RSA-SHA1) and transmission
 * are the transport driver's job.
 */
class FacturaXmlBuilder
{
    public const VERSION = '1.1.0';

    public function build(FiscalDocument $doc, User $venue): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $root = $dom->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        // ---- infoTributaria -------------------------------------------------
        $info = $this->el($dom, $root, 'infoTributaria');
        $this->text($dom, $info, 'ambiente', (string) $doc->ambiente);
        $this->text($dom, $info, 'tipoEmision', '1');
        $this->text($dom, $info, 'razonSocial', $this->clean($venue->fiscal_razon_social, 300));
        if ($this->present($venue->fiscal_nombre_comercial)) {
            $this->text($dom, $info, 'nombreComercial', $this->clean($venue->fiscal_nombre_comercial, 300));
        }
        $this->text($dom, $info, 'ruc', $venue->fiscal_ruc);
        $this->text($dom, $info, 'claveAcceso', $doc->clave_acceso);
        $this->text($dom, $info, 'codDoc', $doc->doc_type);
        $this->text($dom, $info, 'estab', $doc->estab);
        $this->text($dom, $info, 'ptoEmi', $doc->pto_emi);
        $this->text($dom, $info, 'secuencial', str_pad((string) $doc->secuencial, 9, '0', STR_PAD_LEFT));
        $this->text($dom, $info, 'dirMatriz', $this->clean($venue->fiscal_dir_matriz, 300));
        if ($this->present($venue->fiscal_contribuyente_especial)) {
            // Anexo 22 puts agenteRetencion/contribuyenteRimpe last in infoTributaria.
        }
        if (($venue->fiscal_rimpe ?? 'none') === 'emprendedor') {
            $this->text($dom, $info, 'contribuyenteRimpe', 'CONTRIBUYENTE RÉGIMEN RIMPE');
        } elseif (($venue->fiscal_rimpe ?? 'none') === 'negocio_popular') {
            $this->text($dom, $info, 'contribuyenteRimpe', 'CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE');
        }

        // ---- infoFactura ----------------------------------------------------
        $f = $this->el($dom, $root, 'infoFactura');
        $this->text($dom, $f, 'fechaEmision', $doc->fecha_emision->format('d/m/Y'));
        if ($this->present($venue->fiscal_dir_establecimiento)) {
            $this->text($dom, $f, 'dirEstablecimiento', $this->clean($venue->fiscal_dir_establecimiento, 300));
        }
        if ($this->present($venue->fiscal_contribuyente_especial)) {
            // The resolution NUMBER, not SI/NO.
            $this->text($dom, $f, 'contribuyenteEspecial', $this->clean($venue->fiscal_contribuyente_especial, 13));
        }
        $this->text($dom, $f, 'obligadoContabilidad', $venue->fiscal_obligado_contabilidad ? 'SI' : 'NO');
        $this->text($dom, $f, 'tipoIdentificacionComprador', $doc->buyer_id_type);
        $this->text($dom, $f, 'razonSocialComprador', $this->clean($doc->buyer_name, 300));
        $this->text($dom, $f, 'identificacionComprador', $this->clean($doc->buyer_identification, 20));
        if ($this->present($doc->buyer_address)) {
            $this->text($dom, $f, 'direccionComprador', $this->clean($doc->buyer_address, 300));
        }
        $this->text($dom, $f, 'totalSinImpuestos', $this->money($doc->subtotal));
        $this->text($dom, $f, 'totalDescuento', $this->money(0));

        $totalConImpuestos = $this->el($dom, $f, 'totalConImpuestos');
        foreach ($doc->taxes as $tax) {
            $t = $this->el($dom, $totalConImpuestos, 'totalImpuesto');
            $this->text($dom, $t, 'codigo', (string) $tax['codigo']);
            $this->text($dom, $t, 'codigoPorcentaje', (string) $tax['codigo_porcentaje']);
            $this->text($dom, $t, 'baseImponible', $this->money($tax['base']));
            $this->text($dom, $t, 'valor', $this->money($tax['valor']));
        }

        $this->text($dom, $f, 'propina', $this->money($doc->propina));
        $this->text($dom, $f, 'importeTotal', $this->money($doc->importe_total));
        $this->text($dom, $f, 'moneda', 'DOLAR');

        $pagos = $this->el($dom, $f, 'pagos');
        foreach ($doc->payments as $payment) {
            $p = $this->el($dom, $pagos, 'pago');
            $this->text($dom, $p, 'formaPago', (string) $payment['forma_pago']);
            $this->text($dom, $p, 'total', $this->money($payment['total']));
        }

        // ---- detalles -------------------------------------------------------
        $detalles = $this->el($dom, $root, 'detalles');
        foreach ($doc->lines as $line) {
            $d = $this->el($dom, $detalles, 'detalle');
            $this->text($dom, $d, 'codigoPrincipal', $this->clean($line['codigo_principal'], 25));
            $this->text($dom, $d, 'descripcion', $this->clean($line['descripcion'], 300));
            $this->text($dom, $d, 'cantidad', $this->qty($line['cantidad']));
            $this->text($dom, $d, 'precioUnitario', $this->qty($line['precio_unitario']));
            $this->text($dom, $d, 'descuento', $this->money($line['descuento'] ?? 0));
            $this->text($dom, $d, 'precioTotalSinImpuesto', $this->money($line['total_sin_impuesto']));
            $impuestos = $this->el($dom, $d, 'impuestos');
            foreach ($line['impuestos'] as $imp) {
                $i = $this->el($dom, $impuestos, 'impuesto');
                $this->text($dom, $i, 'codigo', (string) $imp['codigo']);
                $this->text($dom, $i, 'codigoPorcentaje', (string) $imp['codigo_porcentaje']);
                $this->text($dom, $i, 'tarifa', $this->money($imp['tarifa_bp'] / 100));
                $this->text($dom, $i, 'baseImponible', $this->money($imp['base']));
                $this->text($dom, $i, 'valor', $this->money($imp['valor']));
            }
        }

        // ---- infoAdicional --------------------------------------------------
        $adicional = [];
        if ($this->present($doc->buyer_email)) {
            $adicional['Email'] = $this->clean($doc->buyer_email, 300);
        }
        if ($this->present($doc->buyer_address)) {
            $adicional['Direccion'] = $this->clean($doc->buyer_address, 300);
        }
        // Mandatory from 26-Sep-2026 (Res. NAC-DGERCGC26-00000027): the RUC of
        // the software provider, when the software is commercialised.
        if ($this->present(config('fiscal.software_provider_ruc'))) {
            $adicional['RUC Proveedor'] = (string) config('fiscal.software_provider_ruc');
        }
        if ($doc->table_id) {
            $adicional['Mesa'] = (string) ($doc->table?->table_number ?? $doc->table_id);
        }
        if ($adicional !== []) {
            $ia = $this->el($dom, $root, 'infoAdicional');
            foreach (array_slice($adicional, 0, 15, true) as $name => $value) {
                $c = $dom->createElement('campoAdicional');
                $c->setAttribute('nombre', $name);
                $c->appendChild($dom->createTextNode($value));
                $ia->appendChild($c);
            }
        }

        return $dom->saveXML();
    }

    private function el(DOMDocument $dom, DOMElement $parent, string $name): DOMElement
    {
        $el = $dom->createElement($name);
        $parent->appendChild($el);

        return $el;
    }

    private function text(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function present(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    /** Collapse whitespace/newlines (pattern [^\n]*) and clamp length. */
    private function clean(?string $value, int $max): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';

        return mb_substr($value, 0, $max);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /** cantidad / precioUnitario: up to six decimals in v1.1.0. */
    private function qty(mixed $amount): string
    {
        return number_format((float) $amount, 6, '.', '');
    }
}
