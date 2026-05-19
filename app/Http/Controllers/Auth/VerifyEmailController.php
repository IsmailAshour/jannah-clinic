<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            // P0 debt: redirects to portal.home (role:customer). When email-verification/confirm-password gates are added to staff routes, use a role-aware redirect (isStaff()? admin.dashboard : portal.home), per User.php hazard docblock & ADR-002.
            return redirect()->route('portal.home');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('portal.home');
    }
}
