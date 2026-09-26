<x-layouts.guest title="Choose a new password">
    <div class="mb-8"><h1 class="text-3xl font-semibold tracking-tight">A fresh start.</h1><p class="muted mt-2">Choose a new password for your account.</p></div>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
        <div><label class="label" for="password">New password</label><input class="input" id="password" name="password" type="password" minlength="12" required autocomplete="new-password"><p class="mt-1 text-xs muted">Use at least 12 characters.</p></div>
        <div><label class="label" for="password_confirmation">Confirm new password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></div>
        <button class="btn btn-primary w-full" type="submit">Reset password</button>
    </form>
</x-layouts.guest>
