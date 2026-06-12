<?php

namespace App\Actions\Tables;

use App\Models\Table;

/**
 * Returns an archived table to service.
 */
class RestoreTable
{
    public function handle(Table $table): Table
    {
        $table->forceFill(['archived_at' => null])->save();

        return $table->refresh();
    }
}
