<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function save(?Plan $plan, array $data, User $actor): Plan
    {
        abort_unless($actor->fresh()->is_platform_admin && $actor->hasVerifiedEmail(), 403);
        return DB::transaction(function () use ($plan, $data, $actor) {
            if ($plan) {
                $businesses = Business::query()->whereHas('subscription', fn ($q) => $q->where('plan_id', $plan->id))->orderBy('id')->lockForUpdate()->get();
                $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
                foreach ($businesses as $business) {
                    $staffCount = $business->memberships()->where('status', 'active')->count() + $business->invitations()->pending()->count();
                    if ($business->branches()->count() > $data['branch_limit'] || $staffCount > $data['staff_limit']) {
                        throw ValidationException::withMessages(['branch_limit' => "This plan cannot be reduced below {$business->name}'s existing branch or staff usage (including pending invitations)."]);
                    }
                    if (array_diff($business->enabled_modules ?? [], $data['modules'] ?? [])) {
                        throw ValidationException::withMessages(['modules' => "Disable the affected modules for {$business->name} before removing them from this plan."]);
                    }
                }
                $before = $plan->only(['name', 'monthly_price', 'branch_limit', 'staff_limit', 'modules', 'is_active']);
                $plan->update($data);
                app(AuditService::class)->record(null, $actor, 'plan.updated', $plan, ['before' => $before, 'after' => $plan->only(array_keys($before))]);
            } else {
                $plan = Plan::create($data);
                app(AuditService::class)->record(null, $actor, 'plan.created', $plan, ['name' => $plan->name]);
            }
            return $plan;
        }, 3);
    }
}
