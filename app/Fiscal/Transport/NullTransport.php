<?php

namespace App\Fiscal\Transport;

use App\Models\FiscalDocument;
use App\Models\User;

/**
 * No gateway configured: the document is built, numbered and stored with
 * its unsigned XML, and parked in "built". Nothing leaves the server. A
 * venue can later configure a driver and re-submit the parked documents
 * in order (their secuenciales are already allocated and must be kept).
 */
class NullTransport implements FiscalTransport
{
    public function submit(FiscalDocument $document, User $venue): FiscalDocument
    {
        $document->status = FiscalDocument::STATUS_BUILT;
        $document->provider = $this->key();
        $document->save();

        return $document;
    }

    public function refresh(FiscalDocument $document, User $venue): FiscalDocument
    {
        return $document;
    }

    public function key(): string
    {
        return 'none';
    }
}
