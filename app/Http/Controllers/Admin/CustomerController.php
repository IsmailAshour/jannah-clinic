<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\AuthService;
use App\Domain\Loyalty\Services\LoyaltyService;
use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Enums\AppointmentStatus;
use App\Enums\MedicalAuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use App\Models\MedicalEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $q = (string) $request->input('q', '');
        $status = (string) $request->input('status', '');

        $query = User::query()
            ->where('role', UserRole::Customer)
            ->with('customerProfile')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($q2) use ($q) {
                $like = '%'.$q.'%';
                $q2->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $customers = $query->paginate(20)->withQueryString();

        $base = User::query()->where('role', UserRole::Customer);
        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'new_this_month' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['q', 'status']),
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, User $customer): Response
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $customer->load('customerProfile');

        $appointments = Appointment::query()
            ->where('customer_id', $customer->id)
            ->with(['service:id,name', 'doctor.user:id,name'])
            ->orderByDesc('start_at')
            ->paginate(15);

        $base = Appointment::query()->where('customer_id', $customer->id);

        $stats = [
            'total' => (clone $base)->count(),
            'completed' => (clone $base)->where('status', AppointmentStatus::Completed)->count(),
            'noShow' => (clone $base)->where('status', AppointmentStatus::NoShow)->count(),
            'lastVisit' => (clone $base)
                ->where('status', AppointmentStatus::Completed)
                ->orderByDesc('start_at')
                ->value('start_at'),
        ];

        $isReceptionist = $request->user()->role === UserRole::Receptionist;

        $addableAppointments = [];
        if ($request->user()->role === UserRole::Doctor && $request->user()->doctorProfile) {
            $addableAppointments = Appointment::query()
                ->where('customer_id', $customer->id)
                ->where('doctor_profile_id', $request->user()->doctorProfile->id)
                ->where('status', AppointmentStatus::Completed)
                ->whereDoesntHave('medicalEntry')
                ->with('service:id,name')
                ->orderByDesc('start_at')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'start_at' => $a->start_at->toIso8601String(),
                    'service' => $a->service->name,
                ])
                ->all();
        }

        $medicalEntries = null;
        if (! $isReceptionist) {
            $medicalEntries = MedicalEntry::query()
                ->whereHas('appointment', fn ($q) => $q->where('customer_id', $customer->id))
                ->with(['appointment:id,start_at', 'prescriptions:id,medical_entry_id'])
                ->latest('created_at')
                ->paginate(15);

            $medicalEntries->through(fn ($e) => [
                'id' => $e->id,
                'date' => $e->appointment->start_at->toIso8601String(),
                'visible_summary' => $e->visible_summary,
                'prescriptions_count' => $e->prescriptions->count(),
            ]);
        }

        $loyaltyBalance = app(LoyaltyService::class)->balance($customer);
        $loyaltyPreview = LoyaltyLedger::query()
            ->where('customer_id', $customer->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'points_delta' => $e->points_delta,
                'balance_after' => $e->balance_after,
                'reason' => $e->reason,
                'notes' => $e->notes,
                'actor_name' => $e->actor?->name,
                'created_at' => $e->created_at->toIso8601String(),
            ])
            ->all();
        $loyaltyTotals = [
            'earned' => (int) LoyaltyLedger::query()->where('customer_id', $customer->id)
                ->where('points_delta', '>', 0)->sum('points_delta'),
            'redeemed' => abs((int) LoyaltyLedger::query()->where('customer_id', $customer->id)
                ->where('points_delta', '<', 0)->sum('points_delta')),
        ];

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'appointments' => $appointments,
            'stats' => $stats,
            'medicalEntries' => $medicalEntries,
            'canViewMedical' => ! $isReceptionist,
            'canEditMedicalProfile' => in_array($request->user()->role, [UserRole::Manager, UserRole::Doctor], true),
            'addableAppointments' => $addableAppointments,
            'loyaltyBalance' => $loyaltyBalance,
            'loyaltyPreview' => $loyaltyPreview,
            'loyaltyTotals' => $loyaltyTotals,
            'canAdjustLoyalty' => $request->user()->role === UserRole::Manager,
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($customer->id)],
            'is_active' => ['boolean'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $customer) {
            $customer->update([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? $customer->is_active,
            ]);

            CustomerProfile::updateOrCreate(
                ['user_id' => $customer->id],
                [
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });

        return back()->with('success', 'تم حفظ بيانات العميل.');
    }

    public function store(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($data['email'] ?? null) && empty($data['phone'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'يجب توفير البريد الإلكتروني أو رقم الجوال.',
                'phone' => 'يجب توفير البريد الإلكتروني أو رقم الجوال.',
            ]);
        }

        $tempPassword = Str::password(16);

        $user = DB::transaction(function () use ($auth, $data, $tempPassword) {
            $created = $auth->registerCustomer(array_merge($data, ['password' => $tempPassword]));
            $hasProfileFields = ! empty($data['date_of_birth'] ?? null)
                || ! empty($data['gender'] ?? null)
                || ! empty($data['notes'] ?? null);
            if ($hasProfileFields) {
                CustomerProfile::updateOrCreate(
                    ['user_id' => $created->id],
                    [
                        'date_of_birth' => $data['date_of_birth'] ?? null,
                        'gender' => $data['gender'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ],
                );
            }

            return $created;
        });

        return redirect()
            ->route('admin.customers.show', $user->id)
            ->with('success', 'تم إنشاء العميل.')
            ->with('temp_password', $tempPassword);
    }

    /**
     * AJAX customer lookup for the admin booking wizard. Matches active
     * customers by name / email / phone (case-insensitive) and returns the
     * top 15 results. Trades the full customer-list bulk-load (slow once the
     * clinic has hundreds of records) for an on-demand search.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->orderBy('name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $results = $query->limit(15)->get(['id', 'name', 'email', 'phone']);

        return response()->json($results);
    }

    public function toggleActive(User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with(
            'success',
            $customer->is_active ? 'تم تفعيل العميل.' : 'تم تعطيل العميل.'
        );
    }

    public function resetPassword(User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $tempPassword = Str::password(16);
        $customer->update(['password' => Hash::make($tempPassword)]);

        return back()
            ->with('success', 'تمت إعادة ضبط كلمة المرور — شارِكها مع العميل الآن.')
            ->with('temp_password', $tempPassword);
    }

    public function updateMedicalProfile(
        Request $request,
        User $customer,
        AuditLogger $audit
    ): RedirectResponse {
        abort_unless($customer->role === UserRole::Customer, 404);
        abort_unless(
            in_array($request->user()->role, [UserRole::Manager, UserRole::Doctor], true),
            403,
        );

        $data = $request->validate([
            'chronic_conditions' => 'nullable|string|max:5000',
            'allergies' => 'nullable|string|max:5000',
        ]);

        DB::transaction(function () use ($customer, $data, $audit) {
            $profile = CustomerProfile::firstOrCreate(['user_id' => $customer->id]);
            $profile->fill($data);
            $dirty = array_keys($profile->getDirty());
            $profile->save();
            if ($dirty !== []) {
                $audit->record(
                    MedicalAuditAction::ProfileMedicalUpdated,
                    $profile,
                    $customer,
                    $dirty,
                );
            }
        });

        return back()->with('success', 'تم تحديث الملف الطبي.');
    }
}
