<x-layouts.guest title="Your team invitation">
    <div class="mb-8"><p class="eyebrow">YOU'RE INVITED</p><h1 class="text-3xl font-semibold tracking-tight">Join {{ $invitation->business->name }}.</h1><p class="muted mt-3">You've been invited as {{ str_replace('_', ' ', $invitation->role) }}. This invitation expires {{ $invitation->expires_at->diffForHumans() }}.</p></div>
    @guest
        <p class="muted mb-5">Sign in or create an account using the email address this invitation was sent to.</p>
        <a class="btn btn-primary w-full" href="{{ route('login') }}">Sign in to accept</a>
        <a class="btn btn-secondary mt-3 w-full" href="{{ route('register') }}">Create an account</a>
    @else
        @if(! auth()->user()->hasVerifiedEmail())
            <a class="btn btn-primary w-full" href="{{ route('verification.notice') }}">Verify your email first</a>
        @elseif(strtolower(auth()->user()->email) !== strtolower($invitation->email))
            <p class="muted mb-5">Your signed-in account does not match this invitation. Sign out, then use the invited email address.</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary w-full" type="submit">Sign out</button></form>
        @else
            <form method="POST" action="{{ route('invitations.accept', $token) }}">@csrf<button class="btn btn-primary w-full" type="submit">Accept invitation</button></form>
        @endif
    @endguest
</x-layouts.guest>
