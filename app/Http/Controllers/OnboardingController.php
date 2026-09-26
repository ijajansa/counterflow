<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function create()
    {
        return view('onboarding.create');
    }

    public function store(Request $request, OnboardingService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['retail', 'grocery', 'clothing', 'restaurant'])],
            'branch_name' => ['required', 'string', 'max:120'],
            'currency' => ['required', Rule::in(['INR', 'USD', 'EUR', 'GBP', 'AED', 'CAD', 'AUD'])],
            'timezone' => ['required', 'timezone'],
        ]);
        $business = $service->create($request->user(), $data);
        $request->session()->put('active_business_id', $business->id);
        $request->session()->forget('active_branch_id');
        return redirect()->route('business.dashboard')->with('status', 'Your business is ready. Welcome to DukaanPOS.');
    }
}
