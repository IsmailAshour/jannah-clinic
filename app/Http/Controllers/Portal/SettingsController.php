<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Notification\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Portal/Settings/Index', []);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($request->input('password'))]);

        $this->notifications->securityPasswordChanged($user);

        return back()->with('success', 'تمّ تغيير كلمة المرور.');
    }
}
