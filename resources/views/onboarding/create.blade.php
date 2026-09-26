<x-layouts.guest title="Set up your business">
    <div class="mb-8"><p class="eyebrow">WELCOME TO DUKAANPOS</p><h1 class="text-3xl font-semibold tracking-tight">Your next chapter starts here.</h1><p class="muted mt-3">Set up your business and first branch. Your owner access and 14-day trial are created together.</p></div>
    <form method="POST" action="{{ route('onboarding.store') }}" class="space-y-5">@csrf
        <div><label class="label" for="name">Business name</label><input class="input" id="name" name="name" value="{{ old('name') }}" maxlength="160" required autofocus placeholder="e.g. Green Basket"></div>
        <div><label class="label" for="type">Business type</label><select class="input" id="type" name="type" required>@foreach(['retail' => 'General retail', 'grocery' => 'Grocery', 'clothing' => 'Clothing', 'restaurant' => 'Restaurant'] as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="label" for="branch_name">First branch name</label><input class="input" id="branch_name" name="branch_name" value="{{ old('branch_name', 'Main branch') }}" maxlength="120" required></div>
        <div class="grid gap-5 sm:grid-cols-2"><div><label class="label" for="currency">Currency</label><select class="input" id="currency" name="currency">@foreach(['INR', 'USD', 'EUR', 'GBP', 'AED', 'CAD', 'AUD'] as $currency)<option @selected(old('currency', 'INR') === $currency)>{{ $currency }}</option>@endforeach</select></div><div><label class="label" for="timezone">Timezone</label><select class="input" id="timezone" name="timezone">@foreach(timezone_identifiers_list() as $timezone)<option @selected(old('timezone', 'Asia/Kolkata') === $timezone)>{{ $timezone }}</option>@endforeach</select></div></div>
        <button class="btn btn-primary w-full" type="submit">Create my business</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm muted underline" type="submit">Sign out</button></form>
</x-layouts.guest>
