<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * private-kitchen: the kitchen display (new orders, item progress).
 */
class KitchenChannel
{
    public function join(User $user): bool
    {
        return $user->can('kitchen.view');
    }
}
