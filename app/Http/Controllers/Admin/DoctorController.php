<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\AuthService;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class DoctorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Doctors/Index', [
            'doctors' => DoctorProfile::with(['user', 'services'])->orderBy('display_order')->orderBy('id')->get(),
            'services' => Service::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'specialty' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'is_bookable' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'services' => ['array'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            $user = app(AuthService::class)->createStaff($data, UserRole::Doctor);
            $doc = DoctorProfile::create([
                'user_id' => $user->id,
                'specialty' => $data['specialty'],
                'bio' => $data['bio'] ?? null,
                'is_bookable' => $data['is_bookable'] ?? true,
                'display_order' => $data['display_order'] ?? 0,
            ]);
            foreach ($data['services'] ?? [] as $s) {
                $doc->services()->attach($s['service_id'], ['price_override' => $s['price_override'] ?? null]);
            }
        });

        return back();
    }

    public function update(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,id,'.$doctor->user_id],
            'specialty' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'is_bookable' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'services' => ['array'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $doctor) {
            $doctor->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);
            $doctor->update([
                'specialty' => $data['specialty'],
                'bio' => $data['bio'] ?? null,
                'is_bookable' => $data['is_bookable'] ?? true,
                'display_order' => $data['display_order'] ?? 0,
            ]);
            $doctor->services()->sync(
                collect($data['services'] ?? [])->mapWithKeys(
                    fn ($s) => [$s['service_id'] => ['price_override' => $s['price_override'] ?? null]]
                )
            );
        });

        return back();
    }

    public function destroy(DoctorProfile $doctor): RedirectResponse
    {
        try {
            $doctor->delete();
            $doctor->user->delete();
        } catch (QueryException $e) { // @phpstan-ignore catch.neverThrown (FK constraint — thrown at runtime by Postgres; SQLite tests skip it)
            return back()->withErrors(['delete' => 'لا يمكن حذف طبيب مرتبط بمواعيد.']);
        }

        return back();
    }
}
