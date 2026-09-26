@props(['title' => 'Welcome'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · DukaanPOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased">
    <a href="#main-content" class="skip-link">Skip to content</a>
    <div class="grid min-h-screen lg:grid-cols-2">
        <aside class="relative hidden overflow-hidden bg-slate-950 p-14 text-white lg:flex lg:flex-col lg:justify-between">
            <a href="{{ url('/') }}" class="relative z-10 text-2xl"><x-brand /></a>
            <div class="relative z-10 max-w-lg py-20">
                <p class="mb-7 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.22em] text-indigo-300"><span class="h-2 w-2 rounded-full bg-indigo-400"></span> Your business, in one place</p>
                <h1 class="text-5xl font-semibold leading-[1.13] tracking-tight xl:text-6xl">A little less admin.<br><span class="text-indigo-300">A lot more possibility.</span></h1>
                <p class="mt-7 max-w-md text-lg leading-8 text-slate-400">Bring your branches, people, and business settings together. A clear foundation for your next chapter.</p>
                <div class="mt-12 grid grid-cols-3 gap-5 border-t border-white/10 pt-7">
                    <div><x-icon name="store" class="mb-3 text-indigo-300"/><p class="text-sm text-slate-300">Every branch</p></div>
                    <div><x-icon name="users" class="mb-3 text-indigo-300"/><p class="text-sm text-slate-300">Your whole team</p></div>
                    <div><x-icon name="settings" class="mb-3 text-indigo-300"/><p class="text-sm text-slate-300">You're in control</p></div>
                </div>
            </div>
            <p class="relative z-10 text-xs text-slate-500">Built for the businesses that keep our neighbourhoods moving.</p>
            <div aria-hidden="true" class="pointer-events-none absolute -right-64 -top-44 h-[700px] w-[700px] rounded-full border border-indigo-400/10"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -right-48 -top-28 h-[560px] w-[560px] rounded-full border border-indigo-400/10"></div>
        </aside>
        <main id="main-content" class="flex min-h-screen flex-col items-center justify-center px-6 py-12 sm:px-12">
            <a href="{{ url('/') }}" class="mb-12 text-2xl lg:hidden"><x-brand /></a>
            <div class="w-full max-w-md"><x-flash />{{ $slot }}</div>
            <p class="mt-12 text-center text-xs text-slate-400">DukaanPOS · Room for your business to grow</p>
        </main>
    </div>
    @livewireScripts
</body>
</html>
