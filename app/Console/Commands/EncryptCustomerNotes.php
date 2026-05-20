<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptCustomerNotes extends Command
{
    protected $signature = 'medical:encrypt-customer-notes';

    protected $description = 'Re-encrypt plaintext customer_profiles.notes rows (idempotent)';

    public function handle(): int
    {
        $count = 0;
        DB::table('customer_profiles')
            ->whereNotNull('notes')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    try {
                        Crypt::decryptString($row->notes);

                        continue;
                    } catch (\Throwable) {
                        DB::table('customer_profiles')
                            ->where('id', $row->id)
                            ->update(['notes' => Crypt::encryptString($row->notes)]);
                        $count++;
                    }
                }
            });
        $this->info("Re-encrypted {$count} rows.");

        return self::SUCCESS;
    }
}
