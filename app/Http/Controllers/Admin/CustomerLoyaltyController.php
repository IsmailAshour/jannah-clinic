<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Loyalty\Services\LoyaltyService;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyLedger;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerLoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function show(Request $request, User $customer): Response
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $ledger = LoyaltyLedger::query()
            ->where('customer_id', $customer->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Admin/Customers/Loyalty', [
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
            'balance' => $this->loyalty->balance($customer),
            'ledger' => $ledger,
        ]);
    }

    public function adjust(Request $request, User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);
        abort_unless($request->user()->role === UserRole::Manager, 403);

        $v = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $this->loyalty->adjust($customer, (int) $v['delta'], $v['note'], $request->user());

        return back()->with('success', 'تم تعديل الرصيد.');
    }
}
