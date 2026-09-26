<x-layouts.app title="Platform overview">
    <div class="page-heading"><div><p class="eyebrow">PLATFORM ADMINISTRATION</p><h1>Every business, one view.</h1><p class="muted mt-2">Manage workspaces, subscription access, and the plans behind them.</p></div><a href="{{ route('platform.businesses') }}" class="btn btn-primary">View businesses</a></div>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['businesses' => 'Total businesses', 'active' => 'Active subscriptions', 'trial' => 'Open trials', 'suspended' => 'Suspended'] as $key => $label)
            <div class="card"><p class="muted text-sm">{{ $label }}</p><p class="mt-3 text-3xl font-semibold">{{ number_format($stats[$key]) }}</p><p class="mt-3 text-xs muted">{{ $key === 'businesses' ? number_format($stats['staff']).' active team memberships' : 'Current subscription access' }}</p></div>
        @endforeach
    </div>
    <div class="mt-7 grid gap-7 xl:grid-cols-[1.6fr_1fr]">
        <section class="card"><div class="mb-5 flex justify-between gap-3"><h2 class="font-semibold">Recently created businesses</h2><a href="{{ route('platform.businesses') }}" class="text-sm text-teal-700">View all</a></div>
            @forelse($businesses as $business)
                <a href="{{ route('platform.businesses.show', $business) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 py-4 last:border-0"><div><p class="font-medium">{{ $business->name }}</p><p class="mt-1 text-xs muted">{{ ucfirst($business->type) }} · {{ $business->branches_count }} branches · {{ $business->memberships_count }} members</p></div><span class="badge">{{ $business->subscription ? app(\App\Services\SubscriptionService::class)->effectiveStatus($business->subscription) : 'No subscription' }}</span></a>
            @empty
                <div class="py-12 text-center"><h3 class="font-semibold">Your first business starts here</h3><p class="muted mx-auto mt-2 max-w-sm text-sm">Business owners create their workspaces through registration and onboarding. They'll appear here automatically.</p></div>
            @endforelse
        </section>
        <section class="card"><div class="mb-5 flex justify-between gap-3"><h2 class="font-semibold">Administrative activity</h2><a href="{{ route('platform.audit') }}" class="text-sm text-teal-700">Audit log</a></div>
            @forelse($activity as $log)<div class="border-b border-slate-100 py-4 last:border-0"><p class="text-sm font-medium">{{ str_replace('.', ' · ', $log->action) }}</p><p class="mt-1 text-xs muted">{{ $log->business?->name ?? 'Platform' }} · {{ $log->created_at->diffForHumans() }}</p></div>@empty<p class="muted py-12 text-center text-sm">Administrative changes will appear here.</p>@endforelse
        </section>
    </div>
</x-layouts.app>
