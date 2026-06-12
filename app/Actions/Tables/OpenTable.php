<?php

namespace App\Actions\Tables;

use App\Exceptions\DomainActionException;
use App\Models\Table;

/**
 * Opens a table for service. The Table model's updating hook is the single
 * canonical session factory: it creates the new TableSession and rotates
 * the QR token. This action therefore only flips the status — opening a
 * table creates exactly ONE session (the legacy TablesList path used to
 * duplicate the hook's work and create two).
 */
class OpenTable
{
    /**
     * @throws DomainActionException
     */
    public function handle(Table $table): Table
    {
        if ($table->archived_at !== null) {
            throw new DomainActionException(__('Restore the table before opening it.'));
        }

        if (! in_array($table->status, ['closed', 'pending_approval'], true)) {
            throw new DomainActionException(__('Table is already open.'));
        }

        $table->status = 'open';
        $table->save();

        return $table->refresh();
    }
}
