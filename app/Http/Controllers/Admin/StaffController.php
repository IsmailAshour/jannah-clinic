<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\AuthService;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages staff users — Manager + Receptionist roles only. Doctors have
 * their own controller (DoctorController) because they carry a richer
 * profile (schedule, services, image). Customers live elsewhere too.
 *
 * Authorization: gate at the route layer requires role:manager. Additional
 * invariants enforced here defend against access-loss footguns:
 *   - You cannot delete or deactivate yourself.
 *   - You cannot delete or demote the LAST active manager.
 */
class StaffController extends Controller
{
    private const MANAGEABLE_ROLES = ['manager', 'receptionist'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $roleFilter = (string) $request->input('role', '');
        $statusFilter = (string) $request->input('status', '');

        $query = User::query()->whereIn('role', self::MANAGEABLE_ROLES);

        if ($q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }
        if (in_array($roleFilter, self::MANAGEABLE_ROLES, true)) {
            $query->where('role', $roleFilter);
        }
        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $staff = $query
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at']);

        $rows = $staff->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->role->value,
            'is_active' => (bool) $u->is_active,
            'created_at' => $u->created_at?->toIso8601String(),
        ])->all();

        $totals = User::query()
            ->whereIn('role', self::MANAGEABLE_ROLES)
            ->selectRaw("role, count(*) as total, sum(case when is_active then 1 else 0 end) as active_count")
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        $managerCount = (int) ($totals['manager']->total ?? 0);
        $receptionistCount = (int) ($totals['receptionist']->total ?? 0);
        $activeCount = (int) (($totals['manager']->active_count ?? 0) + ($totals['receptionist']->active_count ?? 0));
        $total = $managerCount + $receptionistCount;

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $rows,
            'authedUserId' => $request->user()->id,
            'filters' => [
                'q' => $q,
                'role' => $roleFilter,
                'status' => $statusFilter,
            ],
            'stats' => [
                'total' => $total,
                'managers' => $managerCount,
                'receptionists' => $receptionistCount,
                'active' => $activeCount,
                'inactive' => $total - $activeCount,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone'],
            'role' => ['required', 'in:'.implode(',', self::MANAGEABLE_ROLES)],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return back()->withErrors(['email' => 'يجب توفير بريد إلكتروني أو رقم هاتف على الأقل.']);
        }

        $tempPassword = Str::password(12);
        $user = app(AuthService::class)->createStaff(
            [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $tempPassword,
            ],
            UserRole::from($data['role']),
        );
        $user->is_active = true;
        $user->save();

        return back()->with([
            'success' => 'تمّ إنشاء الموظّف.',
            'temp_password' => $tempPassword,
            'temp_password_for_user_id' => $user->id,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role->value, self::MANAGEABLE_ROLES, true), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone,'.$user->id],
            'role' => ['required', 'in:'.implode(',', self::MANAGEABLE_ROLES)],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return back()->withErrors(['email' => 'يجب توفير بريد إلكتروني أو رقم هاتف على الأقل.']);
        }

        // Don't let the last active manager demote themselves out of management.
        if ($user->role === UserRole::Manager && $data['role'] !== 'manager') {
            $this->guardLastActiveManager($user);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => UserRole::from($data['role']),
        ]);

        return back()->with('success', 'تمّ حفظ التعديلات.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role->value, self::MANAGEABLE_ROLES, true), 404);
        $this->guardSelf($request, $user, 'لا يمكنك تعطيل حسابك الخاصّ.');

        if ($user->is_active && $user->role === UserRole::Manager) {
            $this->guardLastActiveManager($user);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? 'تمّ التفعيل.' : 'تمّ التعطيل.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role->value, self::MANAGEABLE_ROLES, true), 404);

        $tempPassword = Str::password(12);
        $user->update(['password' => Hash::make($tempPassword)]);

        return back()->with([
            'success' => 'تمّت إعادة تعيين كلمة المرور.',
            'temp_password' => $tempPassword,
            'temp_password_for_user_id' => $user->id,
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role->value, self::MANAGEABLE_ROLES, true), 404);
        $this->guardSelf($request, $user, 'لا يمكنك حذف حسابك الخاصّ.');

        if ($user->role === UserRole::Manager) {
            $this->guardLastActiveManager($user);
        }

        $user->delete();

        return back()->with('success', 'تمّ حذف الموظّف.');
    }

    private function guardSelf(Request $request, User $target, string $message): void
    {
        if ($request->user()->id === $target->id) {
            abort(redirect()->back()->withErrors(['staff' => $message]));
        }
    }

    /**
     * Refuses an action that would leave zero active managers in the system.
     */
    private function guardLastActiveManager(User $manager): void
    {
        $otherActiveManagers = User::query()
            ->where('role', UserRole::Manager)
            ->where('is_active', true)
            ->where('id', '!=', $manager->id)
            ->count();
        if ($otherActiveManagers === 0) {
            abort(redirect()->back()->withErrors(['staff' => 'لا يمكنك تنفيذ هذا الإجراء — هذا آخر مدير نشط في النظام.']));
        }
    }
}
