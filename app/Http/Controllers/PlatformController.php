<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'businesses' => Business::count(),
            'active' => Subscription::where('status', 'active')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count(),
            'trial' => Subscription::where('status', 'trial')->where('trial_ends_at', '>', now())->count(),
            'suspended' => Subscription::where('status', 'suspended')->count(),
            'staff' => Membership::where('status', 'active')->count(),
        ];
        $businesses = Business::with('subscription.plan')->withCount(['branches', 'memberships'])->latest()->limit(6)->get();
        $activity = AuditLog::with(['user', 'business'])->latest('id')->limit(6)->get();
        return view('platform.dashboard', compact('stats', 'businesses', 'activity'));
    }

    public function businesses(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $businesses = Business::with('subscription.plan')->withCount(['branches', 'memberships'])
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->latest()->paginate(15)->withQueryString();
        return view('platform.businesses', compact('businesses'));
    }

    public function show(Business $business)
    {
        $business->load(['subscription.plan', 'branches', 'memberships.user']);
        $plans = Plan::where('is_active', true)->orderBy('monthly_price')->get();
        return view('platform.business', compact('business', 'plans'));
    }

    public function updateSubscription(Request $request, Business $business, SubscriptionService $service)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'status' => ['required', Rule::in(['trial', 'active', 'expired', 'suspended'])],
            'ends_at' => ['nullable', 'date', Rule::requiredIf($request->input('status') === 'trial')],
        ]);
        if (in_array($data['status'], ['active', 'trial']) && ! empty($data['ends_at'])) {
            $request->validate(['ends_at' => ['after:now']]);
        }
        $service->change($business, Plan::findOrFail($data['plan_id']), $data['status'], $data['ends_at'] ?? null, $request->user());
        return back()->with('status', 'Subscription updated and recorded in the audit log.');
    }

    public function plans()
    {
        return view('platform.plans', ['plans' => Plan::withCount('subscriptions')->orderBy('monthly_price')->get()]);
    }

    public function storePlan(Request $request, PlanService $service)
    {
        $service->save(null, $this->planData($request), $request->user());
        return back()->with('status', 'Plan created.');
    }

    public function updatePlan(Request $request, Plan $plan, PlanService $service)
    {
        $service->save($plan, $this->planData($request, $plan), $request->user());
        return back()->with('status', 'Plan updated.');
    }

    public function audit(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $logs = AuditLog::with(['business', 'user'])->when($data['q'] ?? null, fn ($q, $term) => $q->where('action', 'like', '%'.$term.'%'))
            ->latest('id')->paginate(20)->withQueryString();
        return view('platform.audit', compact('logs'));
    }

    private function planData(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('plans', 'slug')->ignore($plan)],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'branch_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'staff_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'modules' => ['sometimes', 'array'], 'modules.*' => [Rule::in(['sales', 'inventory', 'restaurant', 'clothing'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        return [...$data, 'modules' => array_values(array_unique($data['modules'] ?? [])), 'is_active' => $request->boolean('is_active')];
    }
}
