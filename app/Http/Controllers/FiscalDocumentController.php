<?php

namespace App\Http\Controllers;

use App\Actions\Fiscal\IssueFactura;
use App\Exceptions\DomainActionException;
use App\Models\FiscalDocument;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Web surface of the fiscal ledger: issue a factura from the bill, view
 * the RIDE, download the XML.
 */
class FiscalDocumentController extends Controller
{
    public function issue(Request $request, Table $table, IssueFactura $issue)
    {
        Gate::authorize('issue', [FiscalDocument::class, $table]);

        try {
            $document = $issue->handle($table, $request->user());
        } catch (DomainActionException $e) {
            return redirect()->route('tables.bill', $table)->withErrors(['factura' => $e->getMessage()]);
        }

        return redirect()->route('fiscal.ride', $document);
    }

    /**
     * RIDE — the printable representation. Legally valid alongside the
     * XML (Res. 233 §9.19); the authorisation number is the clave de acceso.
     */
    public function ride(FiscalDocument $document)
    {
        Gate::authorize('view', $document);

        $document->load(['table', 'session']);
        $venue = $document->editor;

        return view('fiscal.ride', [
            'document' => $document,
            'venue' => $venue,
            'currency' => $venue ? $venue->currencySymbol() : '$',
            'width' => request('w') === '58' ? 58 : 80,
        ]);
    }

    public function xml(FiscalDocument $document)
    {
        Gate::authorize('view', $document);

        // Prefer SRI's authorised copy — the one the venue must retain.
        $xml = $document->xml_authorized ?: $document->xml_signed ?: $document->xml_unsigned;
        abort_if($xml === null, 404);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$document->clave_acceso.'.xml"',
        ]);
    }
}
