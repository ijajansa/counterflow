<?php

namespace Tests\Feature;

use App\Livewire\StaffTable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BusinessService;
use App\Services\OnboardingService;
use App\Services\StaffService;
use App\Services\SubscriptionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherOwner;
    private Business $business;
    private Business $otherBusiness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $this->business = $this->onboard($this->owner, 'Neighbourhood Grocers');
        $this->otherBusiness = $this->onboard($this->otherOwner, 'Other Private Business');
    }

    private function onboard(User $user, string $name): Business
    {
        return app(OnboardingService::class)->create($user, [
            'name' => $name, 'type' => 'grocery', 'branch_name' => 'Main store',
            'currency' => 'INR', 'timezone' => 'Asia/Kolkata',
        ]);
    }

    private function loginOwner(): static
    {
        return $this->actingAs($this->owner)->withSession([
            'active_business_id' => $this->business->id,
            'active_branch_id' => $this->business->branches()->first()->id,
        ]);
    }

    private function staff(string $role = 'cashier'): User
    {
        $user = User::factory()->create();
        $membership = $this->business->memberships()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $membership->branches()->attach($this->business->branches()->first()->id, ['business_id' => $this->business->id]);
        return $user;
    }

    private function invitation(User $user, string $token = 'a-long-private-test-token'): Invitation
    {
        $invitation = $this->business->invitations()->create([
            'email' => $user->email, 'role' => 'cashier', 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(), 'invited_by' => $this->owner->id,
        ]);
        $invitation->branches()->attach($this->business->branches()->first()->id, ['business_id' => $this->business->id]);
        return $invitation;
    }

    public function test_onboarding_creates_owner_branch_trial_and_audit_atomically(): void
    {
        $this->assertSame('owner', $this->business->memberships()->sole()->role);
        $this->assertSame($this->owner->id, $this->business->memberships()->sole()->user_id);
        $this->assertSame('MAIN', $this->business->branches()->sole()->code);
        $this->assertSame('trial', $this->business->subscription->status);
        $this->assertTrue($this->business->subscription->trial_ends_at->isFuture());
        $this->assertDatabaseHas('audit_logs', ['business_id' => $this->business->id, 'action' => 'business.created']);
    }

    public function test_onboarding_rolls_back_every_record_if_audit_write_fails(): void
    {
        $newOwner = User::factory()->create();
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            $this->onboard($newOwner, 'Must roll back');
            $this->fail('Expected transaction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseMissing('businesses', ['name' => 'Must roll back']);
        $this->assertDatabaseMissing('memberships', ['user_id' => $newOwner->id]);
        $this->assertDatabaseCount('branches', 2);
        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_onboarding_rejects_invalid_input_without_partial_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), ['name' => 'Bad input', 'type' => 'unknown'])
            ->assertSessionHasErrors(['type', 'branch_name']);
        $this->assertDatabaseMissing('businesses', ['name' => 'Bad input']);
    }

    public function test_unverified_users_cannot_access_business_or_onboarding(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('business.dashboard'))->assertRedirect(route('verification.notice'));
        $this->post(route('onboarding.store'), [])->assertRedirect(route('verification.notice'));
    }

    public function test_session_cannot_select_another_business_without_membership(): void
    {
        $this->actingAs($this->owner)->withSession(['active_business_id' => $this->otherBusiness->id])
            ->get(route('business.dashboard'))->assertForbidden();
    }

    public function test_submitted_business_id_cannot_redirect_a_profile_write(): void
    {
        $this->loginOwner()->patch(route('business.update'), [
            'business_id' => $this->otherBusiness->id, 'name' => 'Updated own shop', 'type' => 'grocery',
            'currency' => 'INR', 'timezone' => 'Asia/Kolkata',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Updated own shop', $this->business->fresh()->name);
        $this->assertSame('Other Private Business', $this->otherBusiness->fresh()->name);
    }

    public function test_cross_tenant_branch_and_staff_ids_return_not_found(): void
    {
        $this->loginOwner()->patch(route('business.branches.update', $this->otherBusiness->branches()->first()->id), [])->assertNotFound();
        $this->patch(route('business.staff.update', $this->otherBusiness->memberships()->first()->id), [])->assertNotFound();
    }

    public function test_business_switch_validates_membership(): void
    {
        $this->loginOwner()->post(route('business.switch'), ['business_id' => $this->otherBusiness->id])->assertNotFound();
    }

    public function test_cashier_can_only_switch_to_assigned_branches(): void
    {
        $user = $this->staff();
        $unassigned = $this->business->branches()->create(['name' => 'Restricted store', 'code' => 'OTHER', 'is_active' => true]);
        $this->actingAs($user)->withSession(['active_business_id' => $this->business->id])
            ->post(route('business.branch.switch'), ['branch_id' => $unassigned->id])->assertNotFound();
        $this->post(route('business.branch.switch'), ['branch_id' => $this->otherBusiness->branches()->first()->id])->assertNotFound();
    }

    public function test_cashier_cannot_access_administrative_pages_or_mutations(): void
    {
        $this->actingAs($this->staff())->withSession(['active_business_id' => $this->business->id]);
        foreach (['business.staff', 'business.edit', 'business.modules', 'business.audit'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->patch(route('business.update'), [])->assertForbidden();
        $this->post(route('business.invitations.store'), [])->assertForbidden();
    }

    public function test_manager_cannot_edit_an_unassigned_branch_or_manage_staff(): void
    {
        $manager = $this->staff('manager');
        $unassigned = $this->business->branches()->create(['name' => 'Elsewhere', 'code' => 'ELSE', 'is_active' => true]);
        $this->actingAs($manager)->withSession(['active_business_id' => $this->business->id])
            ->patch(route('business.branches.update', $unassigned->id), ['name' => 'Hijacked', 'code' => 'ELSE', 'is_active' => true])->assertNotFound();
        $this->get(route('business.staff'))->assertForbidden();
    }

    public function test_inactive_membership_loses_access_immediately(): void
    {
        $this->business->memberships()->where('user_id', $this->owner->id)->update(['status' => 'inactive']);
        $this->loginOwner()->get(route('business.dashboard'))->assertForbidden();
    }

    public function test_staff_branch_assignment_cannot_cross_businesses(): void
    {
        $this->loginOwner()->post(route('business.invitations.store'), [
            'email' => 'new-staff@example.test', 'role' => 'cashier',
            'branch_ids' => [$this->otherBusiness->branches()->first()->id],
        ])->assertSessionHasErrors();
        $this->assertDatabaseMissing('invitations', ['email' => 'new-staff@example.test']);
    }

    public function test_database_constraint_rejects_cross_business_branch_relationship(): void
    {
        $this->expectException(QueryException::class);
        DB::table('branch_membership')->insert([
            'business_id' => $this->business->id,
            'membership_id' => $this->business->memberships()->first()->id,
            'branch_id' => $this->otherBusiness->branches()->first()->id,
        ]);
    }

    public function test_branch_codes_are_unique_per_business(): void
    {
        $this->assertSame('MAIN', $this->business->branches()->first()->code);
        $this->assertSame('MAIN', $this->otherBusiness->branches()->first()->code);
        $this->expectException(QueryException::class);
        $this->business->branches()->create(['name' => 'Duplicate', 'code' => 'MAIN']);
    }

    public function test_branch_limit_is_enforced_on_the_server(): void
    {
        $this->loginOwner()->post(route('business.branches.store'), ['name' => 'Over limit', 'code' => 'EXTRA'])
            ->assertSessionHasErrors();
        $this->assertSame(1, $this->business->branches()->count());
    }

    public function test_pending_invitations_reserve_staff_capacity(): void
    {
        $this->business->subscription->plan->update(['staff_limit' => 2]);
        $this->invitation(User::factory()->create());
        $this->loginOwner()->post(route('business.invitations.store'), [
            'email' => 'over-capacity@example.test', 'role' => 'cashier',
            'branch_ids' => [$this->business->branches()->first()->id],
        ])->assertSessionHasErrors();
        $this->assertSame(1, $this->business->invitations()->count());
    }

    public function test_expired_and_suspended_subscriptions_block_writes_but_allow_subscription_page(): void
    {
        $this->loginOwner();
        foreach (['expired', 'suspended'] as $state) {
            $this->business->subscription()->update(['status' => $state]);
            $this->put(route('business.modules.update'), ['modules' => ['sales']])->assertForbidden();
            $this->get(route('business.subscription'))->assertOk();
        }
    }

    public function test_elapsed_trial_is_enforced_without_scheduler(): void
    {
        $this->business->subscription()->update(['trial_ends_at' => now()->subSecond()]);
        $this->assertSame('expired', app(SubscriptionService::class)->effectiveStatus($this->business->subscription()->first()));
        $this->loginOwner()->post(route('business.branches.store'), ['name' => 'Blocked', 'code' => 'BLOCK'])->assertForbidden();
    }

    public function test_module_requires_both_plan_entitlement_and_enabled_setting(): void
    {
        app(SubscriptionService::class)->assertModule($this->business, 'inventory');
        $this->business->update(['enabled_modules' => ['sales']]);
        $this->expectException(HttpException::class);
        app(SubscriptionService::class)->assertModule($this->business, 'inventory');
    }

    public function test_module_cannot_be_enabled_outside_business_type_or_plan(): void
    {
        $this->loginOwner()->put(route('business.modules.update'), ['modules' => ['restaurant']])->assertSessionHasErrors();
        $this->assertNotContains('restaurant', $this->business->fresh()->enabled_modules);
    }

    public function test_invitation_acceptance_binds_verified_email_and_is_single_use(): void
    {
        $user = User::factory()->create();
        $invitation = $this->invitation($user);
        $membership = app(StaffService::class)->accept($user, 'a-long-private-test-token');
        $this->assertSame($this->business->id, $membership->business_id);
        $this->assertSame('cashier', $membership->role);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->expectException(\Throwable::class);
        app(StaffService::class)->accept($user, 'a-long-private-test-token');
    }

    public function test_an_invitation_cannot_be_accepted_by_a_different_email(): void
    {
        $this->invitation(User::factory()->create());
        $this->actingAs($this->otherOwner)->post(route('invitations.accept', 'a-long-private-test-token'))->assertForbidden();
        $this->assertDatabaseMissing('memberships', ['business_id' => $this->business->id, 'user_id' => $this->otherOwner->id]);
    }

    public function test_expired_invitation_cannot_create_a_membership(): void
    {
        $user = User::factory()->create();
        $this->invitation($user)->update(['expires_at' => now()->subMinute()]);
        $response = $this->actingAs($user)->post(route('invitations.accept', 'a-long-private-test-token'));
        $this->assertContains($response->status(), [404, 410, 422, 302]);
        $this->assertDatabaseMissing('memberships', ['user_id' => $user->id]);
    }

    public function test_suspension_prevents_accepting_outstanding_invitation(): void
    {
        $user = User::factory()->create();
        $this->invitation($user);
        $this->business->subscription()->update(['status' => 'suspended']);
        $this->actingAs($user)->post(route('invitations.accept', 'a-long-private-test-token'))->assertForbidden();
        $this->assertDatabaseMissing('memberships', ['user_id' => $user->id]);
    }

    public function test_owner_cannot_be_demoted_or_disabled_through_staff_update(): void
    {
        $membership = $this->business->memberships()->first();
        $this->loginOwner()->patch(route('business.staff.update', $membership->id), [
            'role' => 'cashier', 'status' => 'inactive', 'branch_ids' => [$this->business->branches()->first()->id],
        ])->assertForbidden();
        $this->assertSame('owner', $membership->fresh()->role);
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_business_owner_cannot_access_platform_or_change_subscription(): void
    {
        $this->loginOwner()->get(route('platform.dashboard'))->assertForbidden();
        $this->patch(route('platform.subscriptions.update', $this->business), [
            'plan_id' => Plan::where('slug', 'growth')->value('id'), 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_platform_admin_can_activate_subscription_with_an_audit_trail(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();
        $plan = Plan::where('slug', 'growth')->first();
        $this->actingAs($admin)->patch(route('platform.subscriptions.update', $this->business), [
            'plan_id' => $plan->id, 'status' => 'active', 'ends_at' => now()->addMonth()->format('Y-m-d'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('subscriptions', ['business_id' => $this->business->id, 'status' => 'active', 'plan_id' => $plan->id]);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $this->business->id, 'user_id' => $admin->id, 'action' => 'subscription.changed']);
        $this->withSession(['active_business_id' => $this->business->id])->get(route('business.dashboard'))->assertForbidden();
    }

    public function test_private_logo_cannot_be_read_from_another_business(): void
    {
        Storage::fake('local');
        $path = 'businesses/'.$this->otherBusiness->id.'/logos/private.png';
        Storage::disk('local')->put($path, 'private-logo-bytes');
        $this->otherBusiness->update(['logo_path' => $path]);
        $this->loginOwner()->get(route('business.logo', ['business_id' => $this->otherBusiness->id]))->assertNotFound();
    }

    public function test_livewire_search_only_returns_current_business_staff(): void
    {
        $this->loginOwner();
        Livewire::test(StaffTable::class)->assertSee($this->owner->email)->assertDontSee($this->otherOwner->email)
            ->set('search', $this->otherOwner->email)->assertDontSee($this->otherOwner->email);
    }

    public function test_livewire_checks_permissions_again_after_role_revocation(): void
    {
        $this->loginOwner();
        $component = Livewire::test(StaffTable::class);
        $this->business->memberships()->where('user_id', $this->owner->id)->update(['status' => 'inactive']);
        $component->set('search', 'test')->assertForbidden();
    }

    public function test_business_and_platform_pages_render_real_data(): void
    {
        $this->loginOwner();
        foreach (['dashboard', 'edit', 'branches', 'staff', 'subscription', 'modules', 'audit'] as $page) {
            $this->get(route('business.'.$page))->assertOk()->assertDontSee($this->otherOwner->email);
        }
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($admin);
        foreach (['dashboard', 'businesses', 'plans', 'audit'] as $page) {
            $this->get(route('platform.'.$page))->assertOk();
        }
        $this->get(route('platform.businesses.show', $this->business))->assertOk();
    }
}
