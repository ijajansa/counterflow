<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessService
{
    public function __construct(private SubscriptionService $subscriptions, private AuditService $audit) {}

    public function update(Business $business, User $actor, array $data): void
    {
        DB::transaction(function () use ($business, $actor, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            Gate::forUser($actor)->authorize('update', $business);
            $this->subscriptions->assertUsable($business);
            $data = Validator::make($data, [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'type' => ['sometimes', 'required', Rule::in(array_keys(config('dukaan.business_types')))],
                'address' => ['nullable', 'string', 'max:2000'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email', 'max:255'],
                'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
                'timezone' => ['sometimes', 'required', 'timezone'],
                'tax_identifier' => ['nullable', 'string', 'max:255'],
                'receipt_footer' => ['nullable', 'string', 'max:2000'],
                'logo_path' => ['nullable', 'string', 'max:255'],
            ])->validate();
            if (isset($data['logo_path']) && (! str_starts_with($data['logo_path'], 'businesses/'.$business->id.'/logos/') || str_contains($data['logo_path'], '..') || str_contains($data['logo_path'], '\\'))) {
                throw ValidationException::withMessages(['logo' => 'The logo must belong to this business.']);
            }
            if (isset($data['type']) && $data['type'] !== $business->type) {
                $plan = $business->subscription()->with('plan')->firstOrFail()->plan;
                $data['enabled_modules'] = array_values(array_intersect($business->enabled_modules, $plan->modules, config('dukaan.type_modules.'.$data['type'], [])));
            }
            $business->fill($data);
            $changed = array_keys($business->getDirty());
            $business->save();
            $this->audit->record($business, $actor, 'business.updated', $business, ['fields' => $changed]);
        });
    }

    public function createBranch(Business $business, User $actor, array $data): Branch
    {
        return DB::transaction(function () use ($business, $actor, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            Gate::forUser($actor)->authorize('manageBranches', $business);
            $this->subscriptions->assertLimit($business, 'branches');
            $data = $this->validateBranch($business, $data);
            $branch = $business->branches()->create($data);
            $membership = $business->memberships()->where('user_id', $actor->id)->firstOrFail();
            $membership->branches()->syncWithoutDetaching([$branch->id => ['business_id' => $business->id]]);
            $this->audit->record($business, $actor, 'branch.created', $branch, ['name' => $branch->name]);

            return $branch;
        });
    }

    public function updateBranch(Branch $branch, User $actor, array $data): void
    {
        DB::transaction(function () use ($branch, $actor, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($branch->business_id);
            $branch = $business->branches()->findOrFail($branch->id);
            Gate::forUser($actor)->authorize('update', $branch);
            $this->subscriptions->assertUsable($business);
            $data = $this->validateBranch($business, $data, $branch);
            if ($branch->is_active && isset($data['is_active']) && ! $data['is_active'] && $business->branches()->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['is_active' => 'Keep at least one branch active.']);
            }
            $before = $branch->only(['name', 'code', 'address', 'is_active']);
            $branch->update($data);
            $this->audit->record($business, $actor, 'branch.updated', $branch, ['before' => $before, 'after' => $branch->only(['name', 'code', 'address', 'is_active'])]);
        });
    }

    public function updateModules(Business $business, User $actor, array $modules): void
    {
        DB::transaction(function () use ($business, $actor, $modules) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            Gate::forUser($actor)->authorize('update', $business);
            $this->subscriptions->assertUsable($business);
            $plan = $business->subscription()->with('plan')->firstOrFail()->plan;
            $allowed = array_intersect($plan->modules, config('dukaan.type_modules.'.$business->type, []));
            Validator::make(['modules' => $modules], ['modules' => ['array'], 'modules.*' => ['required', 'string', Rule::in($allowed), 'distinct']])->validate();
            $before = $business->enabled_modules;
            $business->update(['enabled_modules' => array_values($modules)]);
            $this->audit->record($business, $actor, 'business.modules_updated', $business, ['before' => $before, 'after' => array_values($modules)]);
        });
    }

    private function validateBranch(Business $business, array $data, ?Branch $branch = null): array
    {
        $data = Arr::only($data, ['name', 'code', 'address', 'is_active']);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches', 'code')->where('business_id', $business->id)->ignore($branch?->id)],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();
    }
}
