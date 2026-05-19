<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    // P1 hazard: validates via user email; phone-only customers (email null) will always fail password confirmation. Before any route applies the `password.confirm` middleware in P1, add phone-aware confirmation (resolve by identifier). See ARCHITECTURE.md P0-debt & ADR-002.
    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        // P0 debt: redirects to portal.home (role:customer). When email-verification/confirm-password gates are added to staff routes, use a role-aware redirect (isStaff()? admin.dashboard : portal.home), per User.php hazard docblock & ADR-002.
        return redirect()->route('portal.home');
    }
}
