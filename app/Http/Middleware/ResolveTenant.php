<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);
        if (! $request->user()->memberships()->where('status', 'active')->exists()) {
            if ($request->session()->has('active_business_id')) {
                abort(403, 'Your business membership is no longer active.');
            }

            return redirect()->route('onboarding.create');
        }
        $businessId = $request->session()->get('active_business_id');
        $branchId = $request->session()->get('active_branch_id');
        $this->tenant->resolve($request->user(), $businessId === null ? null : (int) $businessId);
        // A previously selected branch may have been disabled or unassigned by an owner.
        // Only restore it when it still belongs to the current authorized branch set.
        if ($branchId !== null && $this->tenant->branches()->whereKey((int) $branchId)->exists()) {
            $this->tenant->resolve($request->user(), $businessId === null ? null : (int) $businessId, (int) $branchId);
        }
        $request->session()->put('active_business_id', $this->tenant->business()->id);
        $request->session()->put('active_branch_id', $this->tenant->branch()?->id);
        View::share('tenant', $this->tenant);

        return $next($request);
    }
}
