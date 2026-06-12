<?php

namespace App\Actions\Tables;

use App\Exceptions\DomainActionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;

/**
 * Closes a table explicitly (closing is never automatic [F-11]). The Table
 * model's updating hook closes the current session and clears the QR token.
 *
 * A table only closes once every countable (non-cancelled) item is paid —
 * closing kills the QR token and ejects seated guests, so an unpaid balance
 * must be settled (or explicitly settled via SettleTable) first.
 */
class CloseTable
{
    /**
     * @throws DomainActionException
     */
    public function handle(Table $table): Table
    {
        if ($table->status !== 'open') {
            throw new DomainActionException(__('Table is not open.'));
        }

        if ($this->hasUnpaidBalance($table)) {
            throw new DomainActionException(__('Cannot close table until all items are paid.'));
        }

        $table->status = 'closed';
        $table->save();

        return $table->refresh();
    }

    public function hasUnpaidBalance(Table $table): bool
    {
        $orderIds = Order::countable()->where('table_id', $table->id)->pluck('id');

        return OrderItem::whereIn('order_id', $orderIds)
            ->where('is_paid', false)
            ->exists();
    }
}
