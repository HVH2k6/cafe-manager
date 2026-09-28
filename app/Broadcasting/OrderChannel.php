<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * private-order.{orderId}: changes to one order, for its detail page.
 */
class OrderChannel
{
    public function join(User $user, int $orderId): bool
    {
        return $user->can('orders.view');
    }
}
