<x-layouts.app title="Business profile">
    <div class="mb-8"><p class="mb-2 text-sm font-semibold text-indigo-600">BUSINESS SETTINGS</p><h1 class="page-heading">Business profile</h1><p class="muted mt-2">The details that make your business yours.</p></div>
    <form action="{{ route('business.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PATCH')
        <section class="card p-6 sm:p-8" aria-labelledby="identity-heading">
            <h2 id="identity-heading" class="text-lg font-semibold">Business identity</h2><p class="muted mt-1 text-sm">Your name, contact details, and visual identity.</p>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div><label class="label" for="name">Business name</label><input class="input" id="name" name="name" value="{{ old('name', $business->name) }}" required maxlength="160" autocomplete="organization">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="type">Business type</label><select class="input" id="type" name="type" required>@foreach (['retail' => 'General retail', 'grocery' => 'Grocery', 'clothing' => 'Clothing', 'restaurant' => 'Restaurant'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $business->type) === $value)>{{ $label }}</option>@endforeach</select>@error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="email">Business email</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $business->email) }}" maxlength="254" autocomplete="email"></div>
                <div><label class="label" for="phone">Phone number</label><input class="input" id="phone" name="phone" type="tel" value="{{ old('phone', $business->phone) }}" maxlength="40" autocomplete="tel"></div>
                <div class="md:col-span-2"><label class="label" for="address">Business address</label><textarea class="input" id="address" name="address" rows="3" maxlength="1000" autocomplete="street-address">{{ old('address', $business->address) }}</textarea></div>
                <div class="md:col-span-2"><label class="label" for="logo">Business logo</label><div class="flex flex-wrap items-center gap-4">@if ($business->logo_path)<img src="{{ route('business.logo') }}" alt="Current {{ $business->name }} logo" class="h-16 w-16 rounded-xl border border-slate-200 object-contain p-1">@endif<input class="input max-w-md file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1 file:text-indigo-700" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"></div><p class="muted mt-2 text-xs">PNG, JPG, or WebP. Maximum 2 MB. Your logo is stored privately.</p>@error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <section class="card p-6 sm:p-8" aria-labelledby="preferences-heading"><h2 id="preferences-heading" class="text-lg font-semibold">Regional and receipt preferences</h2><div class="mt-6 grid gap-5 md:grid-cols-2">
            <div><label class="label" for="currency">Currency</label><select class="input" id="currency" name="currency" required>@foreach ($currencies as $code => $label)<option value="{{ $code }}" @selected(old('currency', $business->currency) === $code)>{{ $code }} — {{ $label }}</option>@endforeach</select></div>
            <div><label class="label" for="timezone">Timezone</label><select class="input" id="timezone" name="timezone" required>@foreach ($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $business->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><label class="label" for="tax_identifier">Tax registration / GST number</label><input class="input" id="tax_identifier" name="tax_identifier" value="{{ old('tax_identifier', $business->tax_identifier) }}" maxlength="100"><p class="muted mt-2 text-xs">Optional. Use the tax identifier relevant to your business.</p></div>
            <div class="md:col-span-2"><label class="label" for="receipt_footer">Receipt footer</label><textarea class="input" id="receipt_footer" name="receipt_footer" rows="3" maxlength="500">{{ old('receipt_footer', $business->receipt_footer) }}</textarea><p class="muted mt-2 text-xs">Saved for receipts when checkout becomes available.</p></div>
        </div></section>
        <div class="flex justify-end"><button class="btn btn-primary" type="submit">Save business profile</button></div>
    </form>
</x-layouts.app>
