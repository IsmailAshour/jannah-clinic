<?php

return [
    // Home-visit surcharge as a percentage of the (override-or-base) price.
    'home_surcharge_pct' => 30,
    // Minimum minutes between "now" and a bookable slot start.
    'booking_lead_minutes' => 0,
    // Slot grid configuration.
    'slot_minutes' => 30,
    'day_start' => '08:00',
    'day_end' => '22:00',
    'band_split' => '15:00',
    // Bank account info (P2). Defaults are empty; the live values are set at
    // runtime via SettingService (DB-backed override) from the admin Settings page.
    'bank_name' => env('CLINIC_BANK_NAME', ''),
    'bank_account_holder' => env('CLINIC_BANK_ACCOUNT_HOLDER', ''),
    'bank_iban' => env('CLINIC_BANK_IBAN', ''),
    'bank_account_number' => env('CLINIC_BANK_ACCOUNT_NUMBER', ''),
];
