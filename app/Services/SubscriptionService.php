<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(private AuditService $audit) {}

    public function effectiveStatus(Subscription $subscription): string
    {
        if ($subscription->status === 'trial' && (! $subscription->trial_ends_at || $subscription->trial_ends_at->isPast())) {
            return 'expired';
        }
        if ($subscription->status === 'active' && $subscription->ends_at?->isPast()) {
            return 'expired';
        }

        return $subscription->status;
    }

    public function assertUsable(Business $business): void
    {
        $subscription = $business->subscription()->first();
        abort_unless($subscription && in_array($this->effectiveStatus($subscription), ['trial', 'active'], true), 403, 'Your subscription is expired or suspended. Contact the platform administrator.');
    }

    public function assertModule(Business $business, string $module): void
    {
        $this->assertUsable($business);
        $business = $business->fresh();
        $subscription = $business->subscription()->with('plan')->firstOrFail();
        abort_unless(in_array($module, $subscription->plan->modules, true) && in_array($module, $business->enabled_modules, true) && in_array($module, config('dukaan.type_modules.'.$business->type, []), true), 403, 'This module is not enabled for this business and plan.');
    }

    /** Call within a transaction after locking the business when adding a resource. */
    public function assertLimit(Business $business, string $resource): void
    {
        $this->assertUsable($business);
        $plan = $business->subscription()->with('plan')->firstOrFail()->plan;
        if ($resource === 'branches') {
            $count = $business->branches()->count();
            $limit = $plan->branch_limit;
        } elseif ($resource === 'staff') {
            $count = $business->memberships()->where('status', 'active')->count() + $business->invitations()->pending()->count();
            $limit = $plan->staff_limit;
        } else {
            throw new \InvalidArgumentException('Unknown subscription resource.');
        }
        if ($count >= $limit) {
            throw ValidationException::withMessages([$resource => "Your plan allows {$limit} {$resource}. Contact the platform administrator to upgrade."]);
        }
    }

    public function change(Business $business, Plan $plan, string $status, ?string $endsAt, User $actor): void
    {
        abort_unless($actor->fresh()->is_platform_admin && $actor->hasVerifiedEmail(), 403);
        Validator::make(['status' => $status, 'ends_at' => $endsAt], ['status' => ['required', Rule::in(['trial', 'active', 'expired', 'suspended'])], 'ends_at' => ['nullable', 'date']])->validate();
        DB::transaction(function () use ($business, $plan, $status, $endsAt, $actor) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! $plan->is_active && $business->subscription()->value('plan_id') !== $plan->id) {
                throw ValidationException::withMessages(['plan_id' => 'Select an active plan.']);
            }
            if ($business->branches()->count() > $plan->branch_limit || $business->memberships()->where('status', 'active')->count() + $business->invitations()->pending()->count() > $plan->staff_limit) {
                throw ValidationException::withMessages(['plan_id' => 'This plan cannot accommodate the existing branches, active staff, and pending invitations.']);
            }
            $before = $business->subscription()->first()?->only(['plan_id', 'status', 'trial_ends_at', 'ends_at']);
            $subscription = $business->subscription()->updateOrCreate([], [
                'plan_id' => $plan->id,
                'status' => $status,
                'trial_ends_at' => $status === 'trial' ? ($endsAt ? Carbon::parse($endsAt) : now()->addDays(config('dukaan.trial_days'))) : null,
                'ends_at' => $status === 'trial' ? null : ($endsAt ? Carbon::parse($endsAt) : null),
            ]);
            $business->update(['enabled_modules' => array_values(array_intersect($business->enabled_modules, $plan->modules, config('dukaan.type_modules.'.$business->type, [])))]);
            $this->audit->record($business, $actor, 'subscription.changed', $subscription, ['before' => $before, 'after' => $subscription->only(['plan_id', 'status', 'trial_ends_at', 'ends_at']), 'activation' => 'manual']);
        }, 3);
    }
}
