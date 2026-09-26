<x-layouts.guest title="Verify your email">
    <div class="mb-8"><p class="eyebrow">ONE LAST CHECK</p><h1 class="text-3xl font-semibold tracking-tight">Check your inbox.</h1><p class="muted mt-3">We've sent a verification link to <strong class="text-slate-800">{{ auth()->user()->email }}</strong>. Verify your email to open your workspace.</p></div>
    <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn btn-primary w-full" type="submit">Resend verification email</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn btn-secondary w-full" type="submit">Sign out</button></form>
    @if(app()->environment('local'))<p class="muted mt-6 text-xs">Local development: when the mailer is set to log, the verification link is in storage/logs/laravel.log.</p>@endif
</x-layouts.guest>
