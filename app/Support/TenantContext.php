<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Request-scoped, resolved only from an authenticated user's active membership. */
class TenantContext
{
    private ?Business $activeBusiness = null;

    private ?Membership $activeMembership = null;

    private ?Branch $activeBranch = null;

    public function resolve(User $user, ?int $businessId = null, ?int $branchId = null): void
    {
        $this->activeBusiness = $this->activeMembership = $this->activeBranch = null;
        abort_unless($user->hasVerifiedEmail(), 403, 'Verify your email before accessing a business.');
        $query = Membership::query()->with('business')->where('user_id', $user->id)->where('status', 'active');
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }
        $membership = $query->orderBy('id')->first();
        abort_unless($membership, 403, 'You do not have an active membership for this business.');
        $this->activeMembership = $membership;
        $this->activeBusiness = $membership->business;
        if ($branchId !== null) {
            $this->activeBranch = $this->branches()->whereKey($branchId)->first();
            abort_unless($this->activeBranch, 403, 'This branch is not available to you.');
        } else {
            $this->activeBranch = $this->branches()->orderBy('id')->first();
        }
    }

    public function business(): Business
    {
        abort_unless($this->activeBusiness, 403, 'A business context is required.');

        return $this->activeBusiness;
    }

    public function membership(): Membership
    {
        abort_unless($this->activeMembership, 403, 'An active membership is required.');

        return $this->activeMembership;
    }

    public function branch(): ?Branch
    {
        return $this->activeBranch;
    }

    public function branches(): Builder
    {
        $query = Branch::query()->where('business_id', $this->business()->id)->where('is_active', true);
        if ($this->membership()->role !== 'owner') {
            $query->whereIn('id', $this->membership()->branches()->select('branches.id'));
        }

        return $query;
    }
}
