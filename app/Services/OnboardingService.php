<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    public function __construct(private AuditService $audit) {}

    public function create(User $owner, array $data): Business
    {
        abort_unless($owner->hasVerifiedEmail(), 403, 'Verify your email before creating a business.');
        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(config('dukaan.business_types')))],
            'branch_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'timezone'],
        ])->validate();

        return DB::transaction(function () use ($owner, $data) {
            User::query()->lockForUpdate()->findOrFail($owner->id);
            if (Membership::query()->where('user_id', $owner->id)->where('role', 'owner')->exists()) {
                throw ValidationException::withMessages(['name' => 'You already own a business. Open it from your dashboard.']);
            }
            $plan = Plan::query()->where('slug', 'starter')->where('is_active', true)->lockForUpdate()->first();
            if (! $plan || $plan->branch_limit < 1 || $plan->staff_limit < 1) {
                throw ValidationException::withMessages(['name' => 'Business onboarding is currently unavailable. Contact the platform administrator.']);
            }
            $business = Business::create([
                'name' => $data['name'], 'type' => $data['type'], 'currency' => $data['currency'], 'timezone' => $data['timezone'],
                'enabled_modules' => array_values(array_intersect($plan->modules, config('dukaan.type_modules.'.$data['type'], []))),
            ]);
            $membership = $business->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
            $branch = $business->branches()->create(['name' => $data['branch_name'], 'code' => 'MAIN', 'is_active' => true]);
            $membership->branches()->attach($branch->id, ['business_id' => $business->id]);
            $business->subscription()->create(['plan_id' => $plan->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays(config('dukaan.trial_days'))]);
            $this->audit->record($business, $owner, 'business.created', $business, ['branch_id' => $branch->id, 'plan_id' => $plan->id]);

            return $business;
        }, 3);
    }
}
