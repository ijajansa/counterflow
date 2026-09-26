<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->unsignedInteger('branch_limit');
            $table->unsignedInteger('staff_limit');
            $table->json('modules');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['retail', 'grocery', 'clothing', 'restaurant']);
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->char('currency', 3)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('tax_identifier')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('enabled_modules');
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'manager', 'cashier', 'inventory']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('branch_membership', function (Blueprint $table) {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('branch_id');
            $table->primary(['membership_id', 'branch_id']);
            $table->foreign(['business_id', 'membership_id'])->references(['business_id', 'id'])->on('memberships')->cascadeOnDelete();
            $table->foreign(['business_id', 'branch_id'])->references(['business_id', 'id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['manager', 'cashier', 'inventory']);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['business_id', 'email']);
            $table->unique(['business_id', 'id']);
        });

        Schema::create('branch_invitation', function (Blueprint $table) {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invitation_id');
            $table->unsignedBigInteger('branch_id');
            $table->primary(['invitation_id', 'branch_id']);
            $table->foreign(['business_id', 'invitation_id'])->references(['business_id', 'id'])->on('invitations')->cascadeOnDelete();
            $table->foreign(['business_id', 'branch_id'])->references(['business_id', 'id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['trial', 'active', 'expired', 'suspended']);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
        });

        DB::table('plans')->insert([
            ['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 999, 'branch_limit' => 1, 'staff_limit' => 5, 'modules' => json_encode(['sales', 'inventory']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 2499, 'branch_limit' => 5, 'staff_limit' => 25, 'modules' => json_encode(['sales', 'inventory', 'restaurant', 'clothing']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        foreach (['audit_logs', 'subscriptions', 'branch_invitation', 'invitations', 'branch_membership', 'branches', 'memberships', 'businesses', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
    }
};
