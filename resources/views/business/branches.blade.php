<x-layouts.app title="Branches">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4"><div><p class="mb-2 text-sm font-semibold text-indigo-600">BUSINESS LOCATIONS</p><h1 class="page-heading">Branches</h1><p class="muted mt-2">Keep every location connected to your business.</p></div><span class="badge">{{ $branchTotal }} / {{ $business->subscription?->plan?->branch_limit ?? '—' }} branches used</span></div>
    <div class="grid items-start gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden p-0 xl:col-span-2" aria-labelledby="branch-list-heading">
            <div class="border-b border-slate-100 p-5 sm:p-6"><h2 id="branch-list-heading" class="text-lg font-semibold">Your locations</h2><form method="GET" class="mt-4 flex gap-2"><label class="sr-only" for="branch-search">Search branches</label><input class="input" type="search" name="q" id="branch-search" value="{{ $search }}" placeholder="Search name or code" maxlength="120"><button type="submit" class="btn btn-secondary">Search</button></form></div>
            <div class="divide-y divide-slate-100">@forelse ($branches as $branch)
                <details class="group p-5 sm:p-6">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600"><span><span class="block font-semibold">{{ $branch->name }}</span><span class="muted mt-1 block text-xs">{{ $branch->code }} · {{ $branch->address ?: 'No address added' }}</span></span><span class="flex shrink-0 items-center gap-3"><span class="badge">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span><span class="text-sm font-medium text-indigo-600">Edit <span class="inline-block transition group-open:rotate-180" aria-hidden="true">⌄</span></span></span></summary>
                    <form class="mt-5 grid gap-4" method="POST" action="{{ route('business.branches.update', $branch->id) }}">@csrf @method('PATCH')
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="label" for="branch-{{ $branch->id }}-name">Branch name</label><input class="input" id="branch-{{ $branch->id }}-name" name="name" value="{{ $branch->name }}" required maxlength="120"></div><div><label class="label" for="branch-{{ $branch->id }}-code">Branch code</label><input class="input" id="branch-{{ $branch->id }}-code" name="code" value="{{ $branch->code }}" required maxlength="30" pattern="[A-Za-z0-9_-]+"></div></div>
                        <div><label class="label" for="branch-{{ $branch->id }}-address">Address</label><textarea class="input" id="branch-{{ $branch->id }}-address" name="address" maxlength="1000" rows="2">{{ $branch->address }}</textarea></div>
                        <div><label class="label" for="branch-{{ $branch->id }}-status">Status</label><select class="input" id="branch-{{ $branch->id }}-status" name="is_active"><option value="1" @selected($branch->is_active)>Active</option><option value="0" @selected(! $branch->is_active)>Inactive</option></select><p class="muted mt-1 text-xs">Inactive branches remain within your plan limit and cannot be selected by staff.</p></div><div><button type="submit" class="btn btn-primary">Save branch</button></div>
                    </form>
                </details>
            @empty<div class="px-6 py-12 text-center"><h3 class="font-semibold">No branches match your search</h3><p class="muted mt-2 text-sm">Try another name or branch code.</p></div>@endforelse</div>
            @if ($branches->hasPages())<div class="border-t border-slate-100 p-5">{{ $branches->links() }}</div>@endif
        </section>
        <section class="card p-6" aria-labelledby="create-branch-heading"><h2 id="create-branch-heading" class="text-lg font-semibold">Add a branch</h2><p class="muted mt-1 text-sm">Give your next location a home.</p>
            <form class="mt-5 space-y-4" method="POST" action="{{ route('business.branches.store') }}">@csrf
                <div><label class="label" for="new-name">Branch name</label><input class="input" id="new-name" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Market Road">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="new-code">Branch code</label><input class="input" id="new-code" name="code" value="{{ old('code') }}" required maxlength="30" pattern="[A-Za-z0-9_-]+" placeholder="e.g. MARKET"><p class="muted mt-1 text-xs">Letters, numbers, hyphens, and underscores. Unique within your business.</p>@error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="new-address">Address</label><textarea class="input" id="new-address" name="address" rows="3" maxlength="1000">{{ old('address') }}</textarea></div>
                <button type="submit" class="btn btn-primary w-full">Create branch</button>
            </form>
        </section>
    </div>
</x-layouts.app>
