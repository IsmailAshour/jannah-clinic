<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\AuthService;
use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'team_role' => ['nullable', Rule::in(array_column(TeamRole::cases(), 'value'))],
            'is_bookable' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'services' => ['array'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $user = app(AuthService::class)->createStaff($data, UserRole::Doctor);
            $imagePath = $request->hasFile('image')
                ? $request->file('image')->store('team', 'public')
                : null;
            $doc = DoctorProfile::create([
                'user_id' => $user->id,
                'specialty' => $data['specialty'],
                'bio' => $data['bio'] ?? null,
                'team_role' => $data['team_role'] ?? TeamRole::Doctor->value,
                'is_bookable' => $data['is_bookable'] ?? true,
                'display_order' => $data['display_order'] ?? 0,
                'image_path' => $imagePath,
            ]);
            foreach ($data['services'] ?? [] as $s) {
                $doc->services()->attach($s['service_id'], ['price_override' => $s['price_override'] ?? null]);
            }
        });

        return back()->with('success', 'تمت الإضافة.');
    }

    public function update(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($doctor->user_id)],
            'specialty' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'team_role' => ['nullable', Rule::in(array_column(TeamRole::cases(), 'value'))],
            'is_bookable' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'services' => ['array'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $doctor, $request) {
            $doctor->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);
            if (! empty($data['password'])) {
                $doctor->user->update(['password' => Hash::make($data['password'])]);
            }

            $profilePatch = [
                'specialty' => $data['specialty'],
                'bio' => $data['bio'] ?? null,
                'is_bookable' => $data['is_bookable'] ?? true,
                'display_order' => $data['display_order'] ?? 0,
            ];
            if (! empty($data['team_role'])) {
                $profilePatch['team_role'] = $data['team_role'];
            }

            $newImage = $request->file('image');
            $removeImage = (bool) ($data['remove_image'] ?? false);
            if ($newImage !== null) {
                if ($doctor->image_path) {
                    Storage::disk('public')->delete($doctor->image_path);
                }
                $profilePatch['image_path'] = $newImage->store('team', 'public');
            } elseif ($removeImage && $doctor->image_path) {
                Storage::disk('public')->delete($doctor->image_path);
                $profilePatch['image_path'] = null;
            }

            $doctor->update($profilePatch);
            $doctor->services()->sync(
                collect($data['services'] ?? [])->mapWithKeys(
                    fn ($s) => [$s['service_id'] => ['price_override' => $s['price_override'] ?? null]]
                )
            );
        });

        return back()->with('success', 'تم حفظ التعديلات.');
    }

    public function destroy(DoctorProfile $doctor): RedirectResponse
    {
        try {
            $imagePath = $doctor->image_path;
            $doctor->user->delete(); // cascade: deletes doctor_profiles + doctor_service rows
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
        } catch (QueryException $e) {
            return back()->withErrors(['delete' => 'لا يمكن حذف العضو لارتباطه بسجلات أخرى.']);
        }

        return back();
    }
}
