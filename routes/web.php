<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check()
    ? (auth()->user()->is_platform_admin ? 'platform.dashboard' : 'business.dashboard')
    : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:authentication');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:authentication');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:authentication')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:authentication')->name('password.update');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1')->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/verify-email', [AuthController::class, 'verifyNotice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

    Route::prefix('app')->name('business.')->middleware('tenant')->group(function () {
        Route::get('/', [BusinessController::class, 'dashboard'])->name('dashboard');
        Route::get('/settings', [BusinessController::class, 'edit'])->name('edit');
        Route::patch('/settings', [BusinessController::class, 'update'])->name('update');
        Route::get('/logo', [BusinessController::class, 'logo'])->name('logo');
        Route::get('/branches', [BusinessController::class, 'branches'])->name('branches');
        Route::post('/branches', [BusinessController::class, 'storeBranch'])->name('branches.store');
        Route::patch('/branches/{branch}', [BusinessController::class, 'updateBranch'])->whereNumber('branch')->name('branches.update');
        Route::get('/staff', [BusinessController::class, 'staff'])->name('staff');
        Route::post('/invitations', [BusinessController::class, 'invite'])->middleware('throttle:20,1')->name('invitations.store');
        Route::patch('/staff/{membership}', [BusinessController::class, 'updateStaff'])->whereNumber('membership')->name('staff.update');
        Route::delete('/invitations/{invitation}', [BusinessController::class, 'revokeInvitation'])->whereNumber('invitation')->name('invitations.revoke');
        Route::get('/subscription', [BusinessController::class, 'subscription'])->name('subscription');
        Route::get('/modules', [BusinessController::class, 'modules'])->name('modules');
        Route::put('/modules', [BusinessController::class, 'updateModules'])->name('modules.update');
        Route::get('/audit', [BusinessController::class, 'audit'])->name('audit');
        Route::post('/switch-branch', [BusinessController::class, 'switchBranch'])->name('branch.switch');
        Route::post('/switch-business', [BusinessController::class, 'switchBusiness'])->name('switch');
    });

    Route::prefix('platform')->name('platform.')->middleware('platform')->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::get('/businesses', [PlatformController::class, 'businesses'])->name('businesses');
        Route::get('/businesses/{business}', [PlatformController::class, 'show'])->whereNumber('business')->name('businesses.show');
        Route::patch('/businesses/{business}/subscription', [PlatformController::class, 'updateSubscription'])->whereNumber('business')->name('subscriptions.update');
        Route::get('/plans', [PlatformController::class, 'plans'])->name('plans');
        Route::post('/plans', [PlatformController::class, 'storePlan'])->name('plans.store');
        Route::patch('/plans/{plan}', [PlatformController::class, 'updatePlan'])->whereNumber('plan')->name('plans.update');
        Route::get('/audit', [PlatformController::class, 'audit'])->name('audit');
    });
});
