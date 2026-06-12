<?php

namespace App\Actions\Orders;

use App\Exceptions\DomainActionException;
use App\Models\Order;

/**
 * Order status transitions with the board's rules:
 *
 *  - pending → delivered (and back: un-delivering is allowed)
 *  - pending → cancelled (kept in history, excluded from revenue [#12])
 *  - cancelled is FINAL — un-cancelling would silently resurrect revenue,
 *    and a delivered order represents served product so it cannot be
 *    cancelled either.
 */
class ChangeOrderStatus
{
    /**
     * @throws DomainActionException
     */
    public function handle(Order $order, string $status): Order
    {
        if (! in_array($status, ['pending', 'delivered', 'cancelled'], true)) {
            throw new DomainActionException(__('Unknown order status.'));
        }

        if ($order->status === 'cancelled') {
            throw new DomainActionException(__('Cancelled orders are final.'));
        }

        if ($status === 'cancelled' && $order->status !== 'pending') {
            throw new DomainActionException(__('Only pending orders can be cancelled.'));
        }

        $order->status = $status;
        $order->save();

        return $order;
    }
}
