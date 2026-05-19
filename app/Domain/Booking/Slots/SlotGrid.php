<?php

namespace App\Domain\Booking\Slots;

class SlotGrid
{
    /** @return list<string> */
    public static function all(): array
    {
        $step = (int) config('clinic.slot_minutes', 30);
        $start = self::toMin((string) config('clinic.day_start', '08:00'));
        $end = self::toMin((string) config('clinic.day_end', '22:00'));
        $out = [];
        for ($m = $start; $m + $step <= $end; $m += $step) {
            $out[] = self::toHHMM($m);
        }

        return $out;
    }

    /** @return list<string> */
    public static function morning(): array
    {
        $split = self::toMin((string) config('clinic.band_split', '15:00'));

        return array_values(array_filter(self::all(), fn ($s) => self::toMin($s) < $split));
    }

    /** @return list<string> */
    public static function evening(): array
    {
        $split = self::toMin((string) config('clinic.band_split', '15:00'));

        return array_values(array_filter(self::all(), fn ($s) => self::toMin($s) >= $split));
    }

    public static function isValid(string $hhmm): bool
    {
        return in_array($hhmm, self::all(), true);
    }

    /** @return list<string>|null */
    public static function blockFrom(string $start, int $count): ?array
    {
        $all = self::all();
        $i = array_search($start, $all, true);
        if ($i === false || $count < 1) {
            return null;
        }
        $block = array_slice($all, $i, $count);

        return count($block) === $count ? array_values($block) : null;
    }

    private static function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private static function toHHMM(int $min): string
    {
        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }
}
