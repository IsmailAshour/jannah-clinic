<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(User $customer): Response
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

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'appointments' => $appointments,
            'stats' => $stats,
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

    public function toggleActive(User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with(
            'success',
            $customer->is_active ? 'تم تفعيل العميل.' : 'تم تعطيل العميل.'
        );
    }
}
