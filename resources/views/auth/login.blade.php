<x-layouts.guest title="Welcome back">
    <div class="mb-8"><p class="eyebrow">YOUR BUSINESS, CONNECTED</p><h1 class="text-3xl font-semibold tracking-tight">Welcome back.</h1><p class="muted mt-2">Sign in to your DukaanPOS workspace.</p></div>
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div><label class="label" for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
        <div><div class="flex items-center justify-between"><label class="label" for="password">Password</label><a class="text-sm text-teal-700" href="{{ route('password.request') }}">Forgot password?</a></div><input class="input" id="password" name="password" type="password" required autocomplete="current-password"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>
    <p class="mt-6 text-center text-sm muted">New to DukaanPOS? <a class="font-semibold text-teal-700" href="{{ route('register') }}">Create an account</a></p>
</x-layouts.guest>
