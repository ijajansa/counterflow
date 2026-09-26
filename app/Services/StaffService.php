<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(private SubscriptionService $subscriptions, private AuditService $audit) {}

    public function invite(Business $business, User $actor, array $data): Invitation
    {
        $token = Str::random(64);

        return DB::transaction(function () use ($business, $actor, $data, $token) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            Gate::forUser($actor)->authorize('manageStaff', $business);
            $this->subscriptions->assertLimit($business, 'staff');
            $data['email'] = strtolower(trim($data['email'] ?? ''));
            $data = Validator::make($data, [
                'email' => ['required', 'email', 'max:255'],
                'role' => ['required', Rule::in(['manager', 'cashier', 'inventory'])],
                'branch_ids' => ['required', 'array', 'min:1'],
                'branch_ids.*' => ['required', 'integer', 'distinct'],
            ])->validate();
            $branchIds = $this->validateBranches($business, $data['branch_ids']);
            if ($business->memberships()->whereHas('user', fn ($query) => $query->where('email', $data['email']))->exists()) {
                throw ValidationException::withMessages(['email' => 'This person already has a membership. Update their existing access instead.']);
            }
            if ($business->invitations()->pending()->where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'A pending invitation already exists for this email. Revoke it before inviting again.']);
            }
            $invitation = $business->invitations()->create([
                'email' => $data['email'], 'role' => $data['role'], 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(config('dukaan.invitation_days')), 'invited_by' => $actor->id,
            ]);
            $invitation->branches()->sync($this->pivotData($business, $branchIds));
            $this->audit->record($business, $actor, 'staff.invited', $invitation, ['email' => $data['email'], 'role' => $data['role'], 'branch_ids' => $branchIds]);
            DB::afterCommit(fn () => Notification::route('mail', $data['email'])->notify(new StaffInvitation($business->name, $token, $invitation->expires_at->toDayDateTimeString())));

            return $invitation;
        });
    }

    public function accept(User $user, string $token): Membership
    {
        abort_unless($user->hasVerifiedEmail(), 403, 'Verify your email before accepting an invitation.');
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $token))->firstOrFail();

        return DB::transaction(function () use ($user, $invitation) {
            $business = Business::query()->lockForUpdate()->findOrFail($invitation->business_id);
            $invitation = $business->invitations()->pending()->whereKey($invitation->id)->lockForUpdate()->first();
            if (! $invitation) {
                throw ValidationException::withMessages(['invitation' => 'This invitation has expired, been revoked, or already been accepted.']);
            }
            abort_unless(strcasecmp($invitation->email, $user->email) === 0, 403, 'Sign in with the email address that received this invitation.');
            $this->subscriptions->assertUsable($business);
            $branchIds = $this->validateBranches($business, $invitation->branches()->pluck('branches.id')->all());
            if ($business->memberships()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'You already have a membership in this business.']);
            }
            // The pending invitation already reserved one seat; release it before checking capacity.
            $invitation->update(['accepted_at' => now()]);
            $this->subscriptions->assertLimit($business, 'staff');
            $membership = $business->memberships()->create(['user_id' => $user->id, 'role' => $invitation->role, 'status' => 'active']);
            $membership->branches()->sync($this->pivotData($business, $branchIds));
            $this->audit->record($business, $user, 'staff.invitation_accepted', $membership, ['invitation_id' => $invitation->id]);

            return $membership;
        });
    }

    public function update(Membership $membership, User $actor, array $data): void
    {
        DB::transaction(function () use ($membership, $actor, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($membership->business_id);
            $membership = $business->memberships()->findOrFail($membership->id);
            Gate::forUser($actor)->authorize('update', $membership);
            $this->subscriptions->assertUsable($business);
            $data = Validator::make($data, [
                'role' => ['required', Rule::in(['manager', 'cashier', 'inventory'])],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'branch_ids' => ['required', 'array', 'min:1'],
                'branch_ids.*' => ['required', 'integer', 'distinct'],
            ])->validate();
            $branchIds = $this->validateBranches($business, $data['branch_ids']);
            if ($membership->status !== 'active' && $data['status'] === 'active') {
                $this->subscriptions->assertLimit($business, 'staff');
            }
            $before = $membership->only(['role', 'status']);
            $membership->update(['role' => $data['role'], 'status' => $data['status']]);
            $membership->branches()->sync($this->pivotData($business, $branchIds));
            $this->audit->record($business, $actor, 'staff.updated', $membership, ['before' => $before, 'after' => $membership->only(['role', 'status']), 'branch_ids' => $branchIds]);
        });
    }

    public function revoke(Invitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor) {
            $business = Business::query()->lockForUpdate()->findOrFail($invitation->business_id);
            Gate::forUser($actor)->authorize('manageStaff', $business);
            $this->subscriptions->assertUsable($business);
            $invitation = $business->invitations()->whereKey($invitation->id)->firstOrFail();
            if ($invitation->accepted_at || $invitation->revoked_at) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer pending.']);
            }
            $invitation->update(['revoked_at' => now()]);
            $this->audit->record($business, $actor, 'staff.invitation_revoked', $invitation);
        });
    }

    private function validateBranches(Business $business, array $branchIds): array
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        if (empty($branchIds) || $business->branches()->where('is_active', true)->whereIn('id', $branchIds)->count() !== count($branchIds)) {
            throw ValidationException::withMessages(['branch_ids' => 'Choose active branches belonging to this business.']);
        }

        return $branchIds;
    }

    private function pivotData(Business $business, array $branchIds): array
    {
        return array_fill_keys($branchIds, ['business_id' => $business->id]);
    }
}
