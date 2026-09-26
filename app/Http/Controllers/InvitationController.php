<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\StaffService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = Invitation::query()->with('business')->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
        if (! $request->user() || ! $request->user()->hasVerifiedEmail()) {
            $request->session()->put('url.intended', route('invitations.show', $token));
        }
        return view('auth.invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token, StaffService $service)
    {
        $membership = $service->accept($request->user(), $token);
        $request->session()->put('active_business_id', $membership->business_id);
        $request->session()->forget('active_branch_id');
        return redirect()->route('business.dashboard')->with('status', 'Invitation accepted. Welcome to your team.');
    }
}
