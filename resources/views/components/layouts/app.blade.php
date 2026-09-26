@props(['title' => 'Overview'])
@php
    $isPlatform = request()->routeIs('platform.*');
    $currentTenant = $isPlatform ? null : ($tenant ?? null);
    $currentBusiness = $currentTenant?->business();
    $currentMembership = $currentTenant?->membership();
    $nav = $isPlatform ? [
        ['platform.dashboard', 'Overview', 'grid'],
        ['platform.businesses', 'Businesses', 'store'],
        ['platform.plans', 'Plans & limits', 'layers'],
        ['platform.audit', 'Activity log', 'clock'],
    ] : [
        ['business.dashboard', 'Overview', 'grid'],
    ];
    if ($currentMembership && in_array($currentMembership->role, ['owner', 'manager'])) {
        $nav[] = ['business.branches', 'Branches', 'store'];
    }
    if ($currentMembership && $currentMembership->role === 'owner') {
        $nav[] = ['business.staff', 'Team members', 'users'];
        $nav[] = ['business.modules', 'Modules', 'layers'];
        $nav[] = ['business.subscription', 'Plan & subscription', 'card'];
        $nav[] = ['business.edit', 'Business settings', 'settings'];
    }
    if ($currentMembership && in_array($currentMembership->role, ['owner', 'manager'])) {
        $nav[] = ['business.audit', 'Activity log', 'clock'];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · DukaanPOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased" x-data="{ sidebarOpen: false }" x-on:keydown.escape.window="sidebarOpen = false">
    <a href="#main-content" class="skip-link">Skip to content</a>
    <button x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" aria-label="Close navigation"></button>
    <aside id="main-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200/80 bg-white transition-transform lg:translate-x-0" x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <a href="{{ url('/') }}" class="flex h-24 items-center px-7 text-xl"><x-brand /></a>
        <div class="mx-4 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-400">{{ $isPlatform ? 'Administration' : 'Your workspace' }}</p>
            <p class="truncate text-sm font-semibold text-slate-800">{{ $currentBusiness?->name ?? ($isPlatform ? 'Platform console' : 'Getting started') }}</p>
            <p class="mt-1 text-xs capitalize text-slate-500">{{ $currentMembership?->role ?? ($isPlatform ? 'Super administrator' : 'Set up your business') }}</p>
        </div>
        <p class="px-7 pb-3 pt-8 text-[10px] font-bold uppercase tracking-[.15em] text-slate-400">Workspace</p>
        <nav aria-label="Main navigation" class="space-y-1 px-4">
            @foreach($nav as [$route, $label, $icon])
                @php($active = request()->routeIs($route) || ($route === 'platform.businesses' && request()->routeIs('platform.businesses.*')))
                <a href="{{ route($route) }}" @if($active) aria-current="page" @endif class="nav-link {{ $active ? 'nav-link-active' : '' }}"><x-icon :name="$icon"/>{{ $label }}@if($active)<span class="ml-auto h-1.5 w-1.5 rounded-full bg-indigo-600"></span>@endif</a>
            @endforeach
        </nav>
        <div class="mt-auto p-5">
            @if(auth()->user()?->is_platform_admin && !$isPlatform)<a class="nav-link mb-3" href="{{ route('platform.dashboard') }}"><x-icon name="settings"/>Platform console</a>@endif
            <div class="rounded-xl bg-indigo-50 p-4"><p class="text-sm font-semibold text-indigo-950">Made for your everyday.</p><p class="mt-1.5 text-xs leading-5 text-indigo-700/80">One workspace. Your people.<br>Your business, organised.</p></div>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="nav-link w-full" type="submit"><x-icon name="logout"/>Sign out</button></form>
        </div>
    </aside>
    <div class="min-h-screen lg:pl-64">
        <header class="sticky top-0 z-20 flex min-h-20 flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-5 py-4 backdrop-blur sm:px-8">
            <div class="flex items-center gap-3"><button type="button" class="btn btn-secondary px-2.5 lg:hidden" x-on:click="sidebarOpen = !sidebarOpen" x-bind:aria-expanded="sidebarOpen" aria-controls="main-sidebar" aria-label="Toggle navigation"><x-icon name="layers"/></button><div class="text-sm"><span class="hidden text-slate-400 sm:inline">{{ $isPlatform ? 'Platform' : 'Workspace' }} <span class="mx-2 text-slate-300">/</span></span><span class="font-medium">{{ $title }}</span></div></div>
            <div class="flex flex-wrap items-center gap-3 sm:gap-5">
                @if($currentTenant)
                    @php($workspaceMemberships = auth()->user()->memberships()->where('status', 'active')->with('business')->get())
                    @if($workspaceMemberships->count() > 1)
                        <form method="POST" action="{{ route('business.switch') }}" class="flex items-center gap-2">@csrf<label for="business-switch" class="sr-only">Active business</label><select id="business-switch" name="business_id" class="input max-w-40 text-xs">@foreach($workspaceMemberships as $workspace)<option value="{{ $workspace->business_id }}" @selected($workspace->business_id === $currentBusiness->id)>{{ $workspace->business->name }}</option>@endforeach</select><button class="text-xs font-semibold text-indigo-600" type="submit">Switch</button></form>
                    @endif
                    <form method="POST" action="{{ route('business.branch.switch') }}" class="flex items-center gap-2">@csrf<x-icon name="store" class="hidden text-slate-400 sm:block"/><label for="branch-switch" class="sr-only">Active branch</label><select id="branch-switch" name="branch_id" class="input max-w-44 text-xs">@forelse($currentTenant->branches()->get() as $availableBranch)<option value="{{ $availableBranch->id }}" @selected($currentTenant->branch()?->id === $availableBranch->id)>{{ $availableBranch->name }}</option>@empty<option value="">No assigned branch</option>@endforelse</select><button class="text-xs font-semibold text-indigo-600" type="submit">Switch</button></form>
                @else <span class="badge bg-indigo-50 text-indigo-700">{{ $isPlatform ? 'Platform admin' : 'Setup' }}</span> @endif
                <div class="flex items-center gap-2.5 border-l border-slate-200 pl-4"><span class="grid h-9 w-9 place-items-center rounded-full bg-amber-100 text-xs font-bold text-amber-800">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span><span class="hidden text-xs font-semibold sm:block">{{ auth()->user()->name }}</span></div>
            </div>
        </header>
        <main id="main-content" class="mx-auto max-w-[1536px] px-5 py-8 sm:px-8 lg:py-10"><x-flash />{{ $slot }}</main>
        <footer class="px-8 pb-6 text-xs text-slate-400">DukaanPOS <span class="mx-2 text-slate-300">·</span> {{ $currentBusiness?->name ?? 'Business administration' }}</footer>
    </div>
    @livewireScripts
</body>
</html>
