<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    private function role(User $user, Business $business, array $roles): bool
    {
        return $user->hasVerifiedEmail() && $business->memberships()->where('user_id', $user->id)->where('status', 'active')->whereIn('role', $roles)->exists();
    }

    public function view(User $user, Business $business): bool
    {
        return $this->role($user, $business, ['owner', 'manager', 'cashier', 'inventory']);
    }

    public function update(User $user, Business $business): bool
    {
        return $this->role($user, $business, ['owner']);
    }

    public function manageStaff(User $user, Business $business): bool
    {
        return $this->role($user, $business, ['owner']);
    }

    public function manageBranches(User $user, Business $business): bool
    {
        return $this->role($user, $business, ['owner', 'manager']);
    }

    public function viewAudit(User $user, Business $business): bool
    {
        return $this->role($user, $business, ['owner', 'manager']);
    }
}
