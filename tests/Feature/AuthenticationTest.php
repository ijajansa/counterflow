<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invitation;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    public function test_registration_hashes_password_and_cannot_inject_admin_privileges(): void
    {
        $this->post(route('register'), [
            'name' => 'Shop Owner', 'email' => 'Owner@Example.test',
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password',
            'is_platform_admin' => true, 'email_verified_at' => now(),
        ])->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'owner@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_platform_admin);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_a_confirmed_password_of_twelve_characters(): void
    {
        $this->post(route('register'), ['name' => 'New owner', 'email' => 'new@example.test', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_signed_verification_link_verifies_the_authenticated_account(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect(route('onboarding.create'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_cannot_verify_a_different_account(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->actingAs($other)->get($this->verificationUrl($user))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_verified_login_and_logout_work_and_clear_workspace_session(): void
    {
        $user = User::factory()->create(['password' => 'a-long-test-password']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'a-long-test-password'])->assertRedirect(route('onboarding.create'));
        $this->assertAuthenticatedAs($user);
        $this->withSession(['active_business_id' => 123])->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('active_business_id');
        $this->assertGuest();
    }

    public function test_unverified_login_preserves_intended_destination_until_verification(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'a-long-test-password']);
        $this->withSession(['url.intended' => route('onboarding.create')])->post(route('login'), ['email' => $user->email, 'password' => 'a-long-test-password'])
            ->assertRedirect(route('verification.notice'))->assertSessionHas('url.intended', route('onboarding.create'));
    }

    public function test_password_reset_uses_expiring_single_use_broker_token(): void
    {
        $user = User::factory()->create(['password' => 'original-password-123']);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;
            return true;
        });
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'replacement-password-123', 'password_confirmation' => 'replacement-password-123'];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('replacement-password-123', $user->fresh()->password));
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    }

    public function test_invitation_is_preserved_through_registration_and_email_verification(): void
    {
        $owner = User::factory()->create();
        $business = app(OnboardingService::class)->create($owner, ['name' => 'Inviting business', 'type' => 'retail', 'branch_name' => 'Main', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata']);
        $token = str_repeat('a', 64);
        $invitation = Invitation::create(['business_id' => $business->id, 'email' => 'invited@example.test', 'role' => 'cashier', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(), 'invited_by' => $owner->id]);
        $invitation->branches()->attach($business->branches()->sole()->id, ['business_id' => $business->id]);
        $this->get(route('invitations.show', $token))->assertOk()->assertSessionHas('url.intended', route('invitations.show', $token));
        $this->post(route('register'), ['name' => 'Invited colleague', 'email' => 'invited@example.test', 'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password'])->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'invited@example.test')->sole();
        $this->get($this->verificationUrl($user))->assertRedirect(route('invitations.show', $token));
        $this->post(route('invitations.accept', $token))->assertRedirect(route('business.dashboard'))->assertSessionHas('active_business_id', $business->id);
        $this->assertDatabaseHas('memberships', ['business_id' => $business->id, 'user_id' => $user->id, 'role' => 'cashier']);
        $this->assertSame(1, Business::count());
    }

    public function test_demo_command_refuses_production_without_creating_accounts(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('dukaan:demo')->expectsOutput('Demo data is available only when APP_ENV is local or testing.')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_command_is_idempotent_and_has_two_isolated_businesses(): void
    {
        $this->artisan('dukaan:demo')
            ->expectsQuestion('Choose a local demo password (at least 12 characters; used for all five accounts)', 'local-test-password')
            ->expectsQuestion('Confirm local demo password', 'local-test-password')->assertSuccessful();
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('businesses', 2);
        $this->assertDatabaseCount('branches', 3);
        $this->assertDatabaseHas('subscriptions', ['status' => 'active']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'trial']);
        $cashier = User::where('email', 'cashier@greenbasket.test')->sole()->memberships()->sole();
        $this->assertSame(1, $cashier->branches()->count());
        $passwordHash = User::where('email', 'owner@greenbasket.test')->value('password');
        $this->artisan('dukaan:demo')->expectsOutput('Both demo businesses already exist. Existing data and passwords were preserved.')->assertSuccessful();
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('businesses', 2);
        $this->assertSame($passwordHash, User::where('email', 'owner@greenbasket.test')->value('password'));
    }

    public function test_admin_command_uses_prompted_credentials_and_records_creation(): void
    {
        $this->artisan('dukaan:admin')->expectsQuestion('Administrator name', 'Platform Operator')
            ->expectsQuestion('Administrator email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'prompted-test-password')
            ->expectsQuestion('Confirm password', 'prompted-test-password')->assertSuccessful();
        $user = User::where('email', 'admin@example.test')->sole();
        $this->assertTrue($user->is_platform_admin);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('prompted-test-password', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.admin_created', 'user_id' => $user->id]);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }
}
