<?php

namespace App\Actions\Tables;

use App\Exceptions\DomainActionException;
use App\Models\Table;

/**
 * Retires a table that has order history [#5]: hidden from the grid and
 * the QR flow, kept in the database so reporting joins stay intact.
 * Only closed (fully settled) tables can be archived.
 */
class ArchiveTable
{
    /**
     * @throws DomainActionException
     */
    public function handle(Table $table): Table
    {
        if ($table->status !== 'closed') {
            throw new DomainActionException(
                __('Close table :number (all items paid) before archiving it.', [
                    'number' => $table->table_number ?? $table->id,
                ])
            );
        }

        $table->forceFill(['archived_at' => now()])->save();

        return $table->refresh();
    }
}
