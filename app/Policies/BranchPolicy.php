<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }
        $membership = $branch->business->memberships()->where('user_id', $user->id)->where('status', 'active')->first();

        return $membership && ($membership->role === 'owner' || $membership->branches()->whereKey($branch->id)->exists());
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->view($user, $branch) && $branch->business->memberships()->where('user_id', $user->id)->where('status', 'active')->whereIn('role', ['owner', 'manager'])->exists();
    }
}
