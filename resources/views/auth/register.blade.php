<x-layouts.guest title="Create your account">
    <div class="mb-8"><p class="eyebrow">LET'S GET STARTED</p><h1 class="text-3xl font-semibold tracking-tight">Make room to grow.</h1><p class="muted mt-2">Create your account, then set up your business or accept your staff invitation.</p></div>
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div><label class="label" for="name">Your full name</label><input class="input" id="name" name="name" value="{{ old('name') }}" maxlength="120" required autofocus autocomplete="name"></div>
        <div><label class="label" for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" minlength="12" required autocomplete="new-password"><p class="mt-1 text-xs muted">Use at least 12 characters.</p></div>
        <div><label class="label" for="password_confirmation">Confirm password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></div>
        <button class="btn btn-primary w-full" type="submit">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm muted">Already have an account? <a class="font-semibold text-teal-700" href="{{ route('login') }}">Sign in</a></p>
</x-layouts.guest>
