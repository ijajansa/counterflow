<?php

namespace App\Livewire;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class StaffTable extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        // Livewire hydration is a new request: no tenant IDs or roles come from public properties.
        $user = Auth::user();
        abort_unless($user && $user->hasVerifiedEmail(), 403);
        $tenant = app(TenantContext::class);
        $tenant->resolve($user, session('active_business_id'), session('active_branch_id'));
        $business = $tenant->business();
        Gate::authorize('manageStaff', $business);

        $search = mb_substr(trim($this->search), 0, 120);
        $memberships = $business->memberships()->with(['user', 'branches'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $q) => $q->where(fn (Builder $names) => $names->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")->orderBy('id')->paginate(8);

        return view('livewire.staff-table', [
            'memberships' => $memberships,
            'roles' => config('dukaan.roles'),
            'assignableBranches' => $business->branches()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
