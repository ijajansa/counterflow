<x-layouts.app title="Business overview">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="mb-2 text-sm font-semibold text-indigo-600">YOUR WORKSPACE</p>
            <h1 class="page-heading">Good to see you, {{ str($user->name)->before(' ') }}.</h1>
            <p class="muted mt-2">Here is what is happening at {{ $business->name }}.</p>
        </div>
        <span class="badge">{{ ucfirst($business->type) }} · {{ $business->currency }}</span>
    </div>

    @if (in_array($subscriptionStatus, ['expired', 'suspended']))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950" role="status">
            <p class="font-semibold">Your subscription is {{ $subscriptionStatus }}.</p>
            <p class="mt-1 text-sm">Workspace changes are paused. You can still review your account and subscription details.</p>
            <a class="mt-3 inline-block text-sm font-semibold underline" href="{{ route('business.subscription') }}">View subscription</a>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-6"><p class="muted text-sm">Accessible branches</p><p class="mt-3 text-3xl font-semibold tracking-tight">{{ $branchCount }}</p><p class="mt-2 text-xs text-slate-500">Branches available to your account</p></div>
        <div class="card p-6"><p class="muted text-sm">{{ $canManageStaff ? 'Active team members' : 'Your role' }}</p><p class="mt-3 text-3xl font-semibold tracking-tight">{{ $canManageStaff ? $staffCount : $roleLabel }}</p><p class="mt-2 text-xs text-slate-500">{{ $canManageStaff ? 'People in your business' : 'Permissions set by your owner' }}</p></div>
        <div class="card p-6"><p class="muted text-sm">Enabled modules</p><p class="mt-3 text-3xl font-semibold tracking-tight">{{ count($business->enabled_modules ?? []) }}</p><p class="mt-2 text-xs text-slate-500">Configured for your business</p></div>
        <a href="{{ route('business.subscription') }}" class="card block p-6 transition hover:border-indigo-300"><p class="muted text-sm">Current plan</p><p class="mt-3 truncate text-2xl font-semibold tracking-tight">{{ $business->subscription?->plan?->name ?? 'No plan' }}</p><p class="mt-2 text-xs font-medium text-indigo-600">{{ ucfirst($subscriptionStatus) }} · View details →</p></a>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card p-6 xl:col-span-2 sm:p-8" aria-labelledby="sales-heading">
            <div class="flex items-center justify-between"><h2 id="sales-heading" class="text-lg font-semibold">Sales activity</h2><span class="badge">Next phase</span></div>
            <div class="mx-auto max-w-sm py-12 text-center">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600" aria-hidden="true"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5h16M6 16V9m6 7V4m6 12v-5" stroke-linecap="round"/></svg></div>
                <h3 class="text-base font-semibold">Your foundation is ready</h3>
                <p class="muted mt-2 text-sm leading-6">Checkout and sales reporting arrive in a later phase. There are no sales records to display yet. Set up your team, branches, and business preferences to get ready.</p>
            </div>
        </section>
        <section class="card p-6 sm:p-8" aria-labelledby="workspace-heading">
            <h2 id="workspace-heading" class="text-lg font-semibold">Workspace details</h2>
            <dl class="mt-6 space-y-5 text-sm">
                <div><dt class="muted">Active branch</dt><dd class="mt-1 font-medium">{{ $currentBranch?->name ?? 'No active branch assigned' }}</dd></div>
                <div><dt class="muted">Timezone</dt><dd class="mt-1 font-medium">{{ $business->timezone }}</dd></div>
                <div><dt class="muted">Business contact</dt><dd class="mt-1 break-all font-medium">{{ $business->email ?: 'Not added yet' }}</dd></div>
                <div><dt class="muted">Business type</dt><dd class="mt-1 font-medium">{{ ucfirst($business->type) }}</dd></div>
            </dl>
            @can('update', $business)<a href="{{ route('business.edit') }}" class="btn btn-secondary mt-6 w-full">Edit business profile</a>@endcan
        </section>
    </div>

    @if ($canManageStaff)
        <section class="mt-8" aria-labelledby="manage-heading">
            <h2 id="manage-heading" class="mb-4 text-lg font-semibold">Make it yours</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <a class="card p-5 transition hover:border-indigo-300" href="{{ route('business.branches') }}"><span class="text-sm font-semibold">Set up your branches →</span><p class="muted mt-2 text-sm">Manage the places where your business operates.</p></a>
                <a class="card p-5 transition hover:border-indigo-300" href="{{ route('business.staff') }}"><span class="text-sm font-semibold">Bring your team together →</span><p class="muted mt-2 text-sm">Invite staff and assign access to the right branches.</p></a>
                <a class="card p-5 transition hover:border-indigo-300" href="{{ route('business.modules') }}"><span class="text-sm font-semibold">Choose your modules →</span><p class="muted mt-2 text-sm">Configure the capabilities included in your plan.</p></a>
            </div>
        </section>
    @endif
</x-layouts.app>
