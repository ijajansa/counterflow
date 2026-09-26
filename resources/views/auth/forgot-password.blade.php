<x-layouts.guest title="Reset your password">
    <div class="mb-8"><h1 class="text-3xl font-semibold tracking-tight">Forgot your password?</h1><p class="muted mt-3">Enter your account email. We'll send a secure link to choose a new password.</p></div>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">@csrf
        <div><label class="label" for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
        <button class="btn btn-primary w-full" type="submit">Send reset link</button>
    </form>
    <a class="mt-6 block text-center text-sm text-teal-700" href="{{ route('login') }}">Back to sign in</a>
</x-layouts.guest>
