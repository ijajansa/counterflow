<x-layouts.app title="Businesses">
    <div class="page-heading"><div><p class="eyebrow">WORKSPACES</p><h1>Businesses</h1><p class="muted mt-2">Review each business and manage its subscription.</p></div><span class="badge">{{ $businesses->total() }} businesses</span></div>
    <section class="card">
        <form class="mb-6 flex max-w-lg gap-3" method="GET"><label for="q" class="sr-only">Search businesses</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="Search by business name"><button class="btn btn-secondary" type="submit">Search</button></form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Business</th><th>Plan</th><th>Status</th><th>Branches</th><th>Team</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
            @forelse($businesses as $business)<tr><td><p class="font-medium">{{ $business->name }}</p><p class="muted text-xs">{{ ucfirst($business->type) }} · {{ $business->currency }}</p></td><td>{{ $business->subscription?->plan?->name ?? '—' }}</td><td><span class="badge">{{ $business->subscription ? app(\App\Services\SubscriptionService::class)->effectiveStatus($business->subscription) : 'No subscription' }}</span></td><td>{{ $business->branches_count }}</td><td>{{ $business->memberships_count }}</td><td><a class="font-medium text-teal-700" href="{{ route('platform.businesses.show', $business) }}">Manage<span class="sr-only"> {{ $business->name }}</span></a></td></tr>
            @empty<tr><td colspan="6" class="py-12 text-center muted">No businesses match this search.</td></tr>@endforelse
        </tbody></table></div><div class="mt-5">{{ $businesses->links() }}</div>
    </section>
</x-layouts.app>
