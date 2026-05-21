<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:32|unique:users,phone',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (empty($validated['email'] ?? null) && empty($validated['phone'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => __('يجب توفير البريد الإلكتروني أو رقم الجوال.'),
                'phone' => __('يجب توفير البريد الإلكتروني أو رقم الجوال.'),
            ]);
        }

        $user = app(AuthService::class)->registerCustomer($validated);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('public.home');
    }
}
