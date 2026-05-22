<?php

namespace App\Support;

/**
 * Normalises Palestinian phone numbers to E.164 with +970 country code.
 * Used for WhatsApp click-to-chat links on online appointments.
 */
final class PhoneNormalizer
{
    public static function toE164(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            return '';
        }

        // Already country-coded (e.g. 970599...).
        if (str_starts_with($digits, '970')) {
            return '+'.$digits;
        }

        // Local format (e.g. 0599... → drop leading 0).
        if (str_starts_with($digits, '0')) {
            return '+970'.substr($digits, 1);
        }

        // Bare local part (e.g. 599...).
        return '+970'.$digits;
    }
}
