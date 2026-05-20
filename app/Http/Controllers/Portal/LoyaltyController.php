<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Loyalty\Services\LoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = $request->input('tab', 'all');

        $query = LoyaltyLedger::query()->where('customer_id', $user->id);
        if ($tab === 'earn') {
            $query->where('points_delta', '>', 0);
        } elseif ($tab === 'redeem') {
            $query->where('points_delta', '<', 0);
        }
        $ledger = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $summary = [
            'earned' => LoyaltyLedger::query()->where('customer_id', $user->id)
                ->where('points_delta', '>', 0)->sum('points_delta'),
            'redeemed' => abs((int) LoyaltyLedger::query()->where('customer_id', $user->id)
                ->where('points_delta', '<', 0)->sum('points_delta')),
        ];

        return Inertia::render('Portal/Loyalty/Index', [
            'balance' => $this->loyalty->balance($user),
            'summary' => $summary,
            'ledger' => $ledger,
            'tab' => $tab,
        ]);
    }
}
