<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function update(User $user, Membership $membership): bool
    {
        return $membership->role !== 'owner' && (new BusinessPolicy)->manageStaff($user, $membership->business);
    }
}
