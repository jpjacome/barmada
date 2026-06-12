<?php

namespace App\Actions\Tables;

use App\Exceptions\DomainActionException;
use App\Models\ClientInvoice;
use App\Models\Table;

/**
 * Captures (or edits) the client's tax-invoice details for the table's
 * CURRENT session — printed on the bill. [#6]
 *
 * Validation of shape/length happens at the calling boundary (Livewire
 * rules / API request validation); this action sanitizes and persists.
 */
class SaveClientInvoice
{
    /**
     * @param  array{name: string, tax_id: string, address?: ?string, email?: ?string, phone?: ?string}  $data
     *
     * @throws DomainActionException
     */
    public function handle(Table $table, array $data): ClientInvoice
    {
        $session = $table->sessions()
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();

        if (! $session) {
            throw new DomainActionException(
                __('Open the table first — invoice details attach to the current session.')
            );
        }

        return ClientInvoice::updateOrCreate(
            ['table_session_id' => $session->id],
            [
                'table_id' => $table->id,
                'editor_id' => $table->editor_id,
                'name' => strip_tags($data['name']),
                'tax_id' => strip_tags($data['tax_id']),
                'address' => ! empty($data['address']) ? strip_tags($data['address']) : null,
                'email' => ! empty($data['email']) ? $data['email'] : null,
                'phone' => ! empty($data['phone']) ? strip_tags($data['phone']) : null,
            ]
        );
    }
}
