<?php

namespace App\Fiscal\Transport;

use App\Models\User;
use InvalidArgumentException;

/**
 * Resolves the transport driver for a venue from config('fiscal.providers').
 * Adding a gateway = one class implementing FiscalTransport + one config
 * line. Nothing in the ledger, the XML builder or the issuing action
 * changes.
 */
class TransportManager
{
    public function for(User $venue): FiscalTransport
    {
        $key = $venue->fiscal_provider ?: 'none';
        $class = config("fiscal.providers.{$key}");

        if (! $class || ! class_exists($class)) {
            throw new InvalidArgumentException("Unknown fiscal transport [{$key}].");
        }

        $driver = app($class);
        if (! $driver instanceof FiscalTransport) {
            throw new InvalidArgumentException("{$class} does not implement FiscalTransport.");
        }

        return $driver;
    }
}
