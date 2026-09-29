<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * private-tables: table status changes, for the floor plan.
 */
class TablesChannel
{
    public function join(User $user): bool
    {
        return $user->can('tables.view');
    }
}
