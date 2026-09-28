<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * private-orders: every order change, for the order list and the "items ready" pop-up.
 */
class OrdersChannel
{
    public function join(User $user): bool
    {
        return $user->can('orders.view');
    }
}
