<?php

namespace App\Domain\Booking\Services;

use App\Domain\Settings\Services\SettingService;
use App\Enums\DeliveryMode;
use App\Models\DoctorProfile;
use App\Models\DoctorServicePivot;
use App\Models\Service;

class PricingService
{
    public function __construct(private readonly SettingService $settings) {}

    /** @return array{base:string,surcharge:string,total:string} */
    public function quote(DoctorProfile $doctor, Service $service, DeliveryMode $mode): array
    {
        $linked = $doctor->services()
            ->where('services.id', $service->id)
            ->first();
        /** @var DoctorServicePivot|null $pivot — Larastan infers pivot as Pivot base; narrow to concrete type */
        $pivot = $linked?->pivot;
        $override = $pivot?->price_override;
        // bcmath-pure — decimal:2 cast values are numeric strings; bcadd normalises to 2-dp string.
        // CI money gate: no IEEE754 arithmetic on any monetary column — bcmath only.
        $base = bcadd((string) ($override ?? $service->base_price), '0', 2);

        $surcharge = '0.00';
        if ($mode === DeliveryMode::Home) {
            $pct = (string) $this->settings->get('home_surcharge_pct', config('clinic.home_surcharge_pct'));
            $surcharge = bcdiv(bcmul($base, $pct, 4), '100', 2);
        }

        return [
            'base' => $base,
            'surcharge' => $surcharge,
            'total' => bcadd($base, $surcharge, 2),
        ];
    }
}
