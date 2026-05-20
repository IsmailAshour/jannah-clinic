<?php

namespace App\Console\Commands;

use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildLoyaltyBalances extends Command
{
    protected $signature = 'loyalty:rebuild-balances';

    protected $description = 'Recompute customer_profiles.loyalty_balance from the loyalty_ledger sum (idempotent ops command).';

    public function handle(): int
    {
        CustomerProfile::query()->chunkById(500, function ($profiles) {
            foreach ($profiles as $profile) {
                $sum = (int) LoyaltyLedger::query()->where('customer_id', $profile->user_id)->sum('points_delta');
                DB::table('customer_profiles')->where('id', $profile->id)->update(['loyalty_balance' => $sum]);
            }
        });
        $this->info('Loyalty balances rebuilt.');

        return self::SUCCESS;
    }
}
