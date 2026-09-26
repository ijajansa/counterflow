<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OnboardingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateDemoData extends Command
{
    protected $signature = 'dukaan:demo';
    protected $description = 'Create two local demonstration businesses without resetting any data';

    private const ACCOUNTS = [
        'owner@greenbasket.test' => ['Asha Mehta', 'Green Basket', 'owner'],
        'manager@greenbasket.test' => ['Ravi Shah', 'Green Basket', 'manager'],
        'cashier@greenbasket.test' => ['Priya Kumar', 'Green Basket', 'cashier'],
        'owner@threadco.test' => ['Neha Patel', 'Thread & Co', 'owner'],
        'inventory@threadco.test' => ['Arjun Rao', 'Thread & Co', 'inventory'],
    ];

    public function handle(OnboardingService $onboarding, AuditService $audit): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Demo data is available only when APP_ENV is local or testing.');
            return self::FAILURE;
        }
        $existing = User::whereIn('email', array_keys(self::ACCOUNTS))->count();
        if ($existing > 0) {
            $complete = collect(self::ACCOUNTS)->every(function ($account, $email) {
                return Membership::whereHas('user', fn ($q) => $q->where('email', $email))
                    ->whereHas('business', fn ($q) => $q->where('name', $account[1]))->where('role', $account[2])->exists();
            });
            if ($complete) {
                $this->info('Both demo businesses already exist. Existing data and passwords were preserved.');
                $this->showAccounts();
                return self::SUCCESS;
            }
            $this->error('One or more demo email addresses already exist. No data was changed. Use your existing accounts or a separate local database.');
            return self::FAILURE;
        }
        if (Business::whereIn('name', ['Green Basket', 'Thread & Co'])->exists()) {
            $this->error('A demo business name is already in use. No data was changed.');
            return self::FAILURE;
        }
        $starter = Plan::where('slug', 'starter')->where('is_active', true)->first();
        $growth = Plan::where('slug', 'growth')->where('is_active', true)->first();
        if (! $starter || ! $growth || $growth->branch_limit < 2 || $growth->staff_limit < 3 || $starter->staff_limit < 2) {
            $this->error('Demo data requires active Starter and Growth plans with capacity for the demo teams. Run migrations and database seeding first.');
            return self::FAILURE;
        }
        $password = $this->secret('Choose a local demo password (at least 12 characters; used for all five accounts)');
        $confirmation = $this->secret('Confirm local demo password');
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => ['required', 'confirmed', Password::min(12)]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        DB::transaction(function () use ($password, $onboarding, $audit, $growth) {
            $users = [];
            foreach (self::ACCOUNTS as $email => $account) {
                $users[$email] = User::create(['name' => $account[0], 'email' => $email, 'password' => $password]);
                $users[$email]->forceFill(['email_verified_at' => now()])->save();
            }
            $basket = $onboarding->create($users['owner@greenbasket.test'], ['name' => 'Green Basket', 'type' => 'grocery', 'branch_name' => 'Central Market', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata']);
            $basket->update(['address' => '12 Market Road, Pune', 'email' => 'hello@greenbasket.test', 'phone' => '+91 20 5550 0100', 'receipt_footer' => 'Thank you for shopping local.']);
            // Explicit local fixture state; no payment is represented or simulated.
            $basket->subscription()->update(['plan_id' => $growth->id, 'status' => 'active', 'trial_ends_at' => null, 'ends_at' => now()->addYear()]);
            $central = $basket->branches()->firstOrFail();
            $riverside = $basket->branches()->create(['name' => 'Riverside', 'code' => 'RIVER', 'address' => '8 Riverside Lane, Pune', 'is_active' => true]);
            $basket->memberships()->where('role', 'owner')->firstOrFail()->branches()->attach($riverside->id, ['business_id' => $basket->id]);
            $this->addMember($basket, $users['manager@greenbasket.test'], 'manager', [$central->id, $riverside->id]);
            $this->addMember($basket, $users['cashier@greenbasket.test'], 'cashier', [$central->id]);
            $thread = $onboarding->create($users['owner@threadco.test'], ['name' => 'Thread & Co', 'type' => 'clothing', 'branch_name' => 'Studio Store', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata']);
            $thread->update(['address' => '42 Studio Street, Mumbai', 'email' => 'hello@threadco.test', 'receipt_footer' => 'Wear your story. Thank you for visiting.']);
            $this->addMember($thread, $users['inventory@threadco.test'], 'inventory', [$thread->branches()->firstOrFail()->id]);
            $audit->record($basket, null, 'demo.created', $basket, ['source' => 'local_fixture', 'subscription' => 'active']);
            $audit->record($thread, null, 'demo.created', $thread, ['source' => 'local_fixture', 'subscription' => 'trial']);
        });
        $this->info('Created two demo businesses, three branches, and five verified local users. No sales were generated.');
        $this->showAccounts();
        return self::SUCCESS;
    }

    private function addMember(Business $business, User $user, string $role, array $branchIds): void
    {
        $membership = $business->memberships()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $membership->branches()->sync(array_fill_keys($branchIds, ['business_id' => $business->id]));
    }

    private function showAccounts(): void
    {
        $rows = [];
        foreach (self::ACCOUNTS as $email => $account) $rows[] = [$email, $account[1], $account[2]];
        $this->table(['Email', 'Business', 'Role'], $rows);
        $this->line('Use the password you entered. Passwords are never written to source files.');
    }
}
