<x-layouts.app title="Plans and entitlements">
    <div class="page-heading"><div><p class="eyebrow">ACCESS & LIMITS</p><h1>Plans and entitlements</h1><p class="muted mt-2">Define branch capacity, team size, and the modules each plan permits.</p></div></div>
    <div class="mb-6 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">Module entitlements prepare businesses for upcoming POS phases. Sales, inventory, restaurant, and clothing workflows are not included in this foundation release. Monthly prices are reference prices in INR; billing is manual.</div>
    <div class="grid items-start gap-6 xl:grid-cols-2">
        @foreach($plans as $plan)
            <section class="card"><div class="mb-5 flex items-start justify-between"><div><h2 class="text-lg font-semibold">{{ $plan->name }}</h2><p class="muted mt-1 text-xs">{{ $plan->subscriptions_count }} subscribed businesses</p></div><span class="badge">{{ $plan->is_active ? 'Available' : 'Archived' }}</span></div>
                <form method="POST" action="{{ route('platform.plans.update', $plan) }}" class="space-y-4">@csrf @method('PATCH')
                    @include('platform.plan-fields', ['plan' => $plan, 'prefix' => 'plan-'.$plan->id])
                    <button class="btn btn-secondary" type="submit">Save {{ $plan->name }}</button>
                </form>
            </section>
        @endforeach
        <section class="card"><h2 class="mb-5 text-lg font-semibold">Create a plan</h2><form method="POST" action="{{ route('platform.plans.store') }}" class="space-y-4">@csrf @include('platform.plan-fields', ['plan' => null, 'prefix' => 'new'])<button class="btn btn-primary" type="submit">Create plan</button></form></section>
    </div>
</x-layouts.app>
