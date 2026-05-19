<?php

namespace App\Domain\Settings\Services;

use App\Models\Setting;

class SettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = Setting::query()->where('key', $key)->value('value') ?? $default;

        return $value === null ? null : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
