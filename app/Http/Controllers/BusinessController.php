<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Services\BusinessService;
use App\Services\StaffService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessController extends Controller
{
    private const CURRENCIES = ['INR' => 'Indian rupee', 'USD' => 'US dollar', 'EUR' => 'Euro', 'GBP' => 'British pound', 'AED' => 'UAE dirham', 'SGD' => 'Singapore dollar', 'AUD' => 'Australian dollar', 'CAD' => 'Canadian dollar'];

    public function __construct(
        private TenantContext $tenant,
        private BusinessService $businesses,
        private StaffService $staffService,
        private SubscriptionService $subscriptions,
    ) {}

    public function dashboard(Request $request): View
    {
        $business = $this->business('view')->load('subscription.plan');
        $canManageStaff = Gate::allows('manageStaff', $business);

        return view('business.dashboard', [
            'business' => $business,
            'user' => $request->user(),
            'currentBranch' => $this->tenant->branch(),
            'branchCount' => $this->tenant->branches()->count(),
            'staffCount' => $canManageStaff ? $business->memberships()->where('status', 'active')->count() : null,
            'canManageStaff' => $canManageStaff,
            'roleLabel' => config('dukaan.roles.'.$this->tenant->membership()->role),
            'subscriptionStatus' => $this->status($business),
        ]);
    }

    public function edit(): View
    {
        return view('business.edit', [
            'business' => $this->business('update'),
            'currencies' => self::CURRENCIES,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $this->business('update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(config('dukaan.business_types')))],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', Rule::in(array_keys(self::CURRENCIES))],
            'timezone' => ['required', 'timezone:all'],
            'tax_identifier' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        unset($data['logo']);
        $newLogo = null;
        $oldLogo = $business->logo_path;
        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('businesses/'.$business->id.'/logos', 'local');
            if (! $newLogo) {
                throw ValidationException::withMessages(['logo' => 'The logo could not be saved. Please try again.']);
            }
            $data['logo_path'] = $newLogo;
        }

        try {
            $this->businesses->update($business, $request->user(), $data);
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk('local')->delete($newLogo);
            }
            throw $exception;
        }
        if ($newLogo && $oldLogo && str_starts_with($oldLogo, 'businesses/'.$business->id.'/logos/')) {
            Storage::disk('local')->delete($oldLogo);
        }

        return to_route('business.edit')->with('status', 'Business profile saved.');
    }

    public function logo(): StreamedResponse
    {
        $business = $this->business('view');
        $path = $business->logo_path;
        abort_unless($path && str_starts_with($path, 'businesses/'.$business->id.'/logos/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function branches(Request $request): View
    {
        $business = $this->business('manageBranches')->load('subscription.plan');
        $search = $this->search($request);
        $branches = $this->accessibleBranches($business)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(10)->withQueryString();

        return view('business.branches', compact('business', 'branches', 'search') + ['branchTotal' => $business->branches()->count()]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $business = $this->business('manageBranches');
        $this->businesses->createBranch($business, $request->user(), $this->branchInput($request));

        return to_route('business.branches')->with('status', 'Branch created. Assign staff access from Team & access.');
    }

    public function updateBranch(Request $request, int $branch): RedirectResponse
    {
        $business = $this->business('manageBranches');
        $record = $this->accessibleBranches($business)->findOrFail($branch);
        $data = $this->branchInput($request, true);
        $this->businesses->updateBranch($record, $request->user(), $data);
        if (! $data['is_active'] && (int) $request->session()->get('active_branch_id') === $record->id) {
            $request->session()->forget('active_branch_id');
        }

        return to_route('business.branches')->with('status', 'Branch updated.');
    }

    public function staff(): View
    {
        $business = $this->business('manageStaff')->load('subscription.plan');

        return view('business.staff', [
            'business' => $business,
            'activeStaffCount' => $business->memberships()->where('status', 'active')->count(),
            'pendingInvitations' => $business->invitations()->pending()->with('branches')->latest()->get(),
            'assignableBranches' => $business->branches()->where('is_active', true)->orderBy('name')->get(),
            'roles' => config('dukaan.roles'),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $business = $this->business('manageStaff');
        $data = $request->validate($this->staffRules($business) + ['email' => ['required', 'email', 'max:254']]);
        $this->staffService->invite($business, $request->user(), $data);

        return to_route('business.staff')->with('status', 'Invitation sent. The secure link expires in seven days.');
    }

    public function updateStaff(Request $request, int $membership): RedirectResponse
    {
        $business = $this->business('manageStaff');
        $record = $business->memberships()->findOrFail($membership);
        $data = $request->validate($this->staffRules($business) + ['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $this->staffService->update($record, $request->user(), $data);

        return to_route('business.staff')->with('status', 'Team access updated.');
    }

    public function revokeInvitation(Request $request, int $invitation): RedirectResponse
    {
        $business = $this->business('manageStaff');
        $record = $business->invitations()->findOrFail($invitation);
        $this->staffService->revoke($record, $request->user());

        return to_route('business.staff')->with('status', 'Invitation revoked.');
    }

    public function subscription(): View
    {
        $business = $this->business('view')->load('subscription.plan');

        return view('business.subscription', [
            'business' => $business,
            'subscription' => $business->subscription,
            'subscriptionStatus' => $this->status($business),
            'branchCount' => $business->branches()->count(),
            'seatCount' => $business->memberships()->where('status', 'active')->count() + $business->invitations()->pending()->count(),
            'moduleLabels' => config('dukaan.modules'),
        ]);
    }

    public function modules(): View
    {
        $business = $this->business('update')->load('subscription.plan');

        return view('business.modules', [
            'business' => $business,
            'moduleLabels' => config('dukaan.modules'),
            'planModules' => $business->subscription?->plan?->modules ?? [],
            'typeModules' => config('dukaan.type_modules.'.$business->type, []),
            'moduleDescriptions' => [
                'sales' => 'Checkout, customer transactions, and receipts.',
                'inventory' => 'Products, stock levels, and movement between locations.',
                'restaurant' => 'Tables, kitchen orders, and restaurant service.',
                'clothing' => 'Size and colour variants for your clothing catalogue.',
            ],
        ]);
    }

    public function updateModules(Request $request): RedirectResponse
    {
        $business = $this->business('update');
        $data = $request->validate(['modules' => ['sometimes', 'array', 'max:4'], 'modules.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('dukaan.modules')))]]);
        $this->businesses->updateModules($business, $request->user(), $data['modules'] ?? []);

        return to_route('business.modules')->with('status', 'Module preferences saved.');
    }

    public function audit(Request $request): View
    {
        $business = $this->business('viewAudit');
        $search = $this->search($request);
        $records = $business->auditLogs()->with('user')
            ->when($this->tenant->membership()->role !== 'owner', fn (Builder $query) => $query
                ->where('subject_type', (new Branch)->getMorphClass())
                ->whereIn('subject_id', $this->accessibleBranches($business)->select('branches.id')))
            ->when($search !== '', fn (Builder $query) => $query->where('action', 'like', '%'.$search.'%'))
            ->latest('id')->paginate(20)->withQueryString();

        return view('business.audit', compact('business', 'records', 'search'));
    }

    public function switchBranch(Request $request): RedirectResponse
    {
        $this->business('view');
        $data = $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = $this->tenant->branches()->findOrFail($data['branch_id']);
        $request->session()->put('active_branch_id', $branch->id);

        return to_route('business.dashboard')->with('status', 'Switched to '.$branch->name.'.');
    }

    public function switchBusiness(Request $request): RedirectResponse
    {
        $data = $request->validate(['business_id' => ['required', 'integer']]);
        // A submitted ID only selects a verified membership; it never establishes tenancy itself.
        $membership = $request->user()->memberships()->where('business_id', $data['business_id'])->where('status', 'active')->firstOrFail();
        $this->tenant->resolve($request->user(), $membership->business_id);
        $request->session()->put('active_business_id', $membership->business_id);
        $request->session()->forget('active_branch_id');

        return to_route('business.dashboard')->with('status', 'Business workspace switched.');
    }

    private function business(string $ability): Business
    {
        $business = $this->tenant->business();
        Gate::authorize($ability, $business);

        return $business;
    }

    private function status(Business $business): string
    {
        return $business->subscription ? $this->subscriptions->effectiveStatus($business->subscription) : 'expired';
    }

    private function accessibleBranches(Business $business): Builder
    {
        return Branch::query()->where('business_id', $business->id)
            ->when($this->tenant->membership()->role !== 'owner', fn (Builder $query) => $query
                ->whereIn('id', $this->tenant->membership()->branches()->select('branches.id')));
    }

    private function search(Request $request): string
    {
        return trim($request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? '');
    }

    private function branchInput(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
        ] + ($updating ? ['is_active' => ['required', 'boolean']] : []));
    }

    private function staffRules(Business $business): array
    {
        return [
            'role' => ['required', Rule::in(['manager', 'cashier', 'inventory'])],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('business_id', $business->id)->where('is_active', true)],
        ];
    }
}
