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
        // Single-service back-compat: delegate to quoteMulti and rename
        // 'subtotal' to 'base' for callers that haven't migrated yet.
        $multi = $this->quoteMulti($doctor, [$service], $mode);

        return [
            'base' => $multi['subtotal'],
            'surcharge' => $multi['surcharge'],
            'total' => $multi['total'],
        ];
    }

    /**
     * Quote a multi-service visit. Each service contributes its
     * doctor-specific price_override (or base_price) as a 'line'. Subtotal
     * is the bcmath sum. Surcharge for Home delivery applies to the visit
     * as a whole — patients pay one delivery fee, not one per service.
     *
     * @param  iterable<Service>  $services
     * @return array{
     *   lines: list<array{service_id:int, base:string, duration_minutes:int}>,
     *   subtotal: string,
     *   surcharge: string,
     *   total: string
     * }
     */
    public function quoteMulti(DoctorProfile $doctor, iterable $services, DeliveryMode $mode): array
    {
        $lines = [];
        $subtotal = '0.00';

        // Materialise the iterable + collect IDs once so we can fetch every
        // doctor_service pivot in one query (avoids N+1 when 2+ services are
        // priced).
        $svcList = [];
        $ids = [];
        foreach ($services as $service) {
            /** @var Service $service */
            $svcList[] = $service;
            $ids[] = $service->id;
        }
        if ($svcList === []) {
            return ['lines' => [], 'subtotal' => '0.00', 'surcharge' => '0.00', 'total' => '0.00'];
        }
        /** @var array<int,string|null> $overrides */
        $overrides = [];
        foreach ($doctor->services()->whereIn('services.id', $ids)->get(['services.id']) as $s) {
            /** @var Service $s */
            /** @var DoctorServicePivot|null $pivot */
            $pivot = $s->pivot; // @phpstan-ignore-line  Larastan loses pivot type through collection iteration
            $overrides[(int) $s->id] = $pivot?->price_override;
        }

        foreach ($svcList as $service) {
            $override = $overrides[$service->id] ?? null;
            // bcmath-pure — decimal:2 cast values are numeric strings.
            $line = bcadd((string) ($override ?? $service->base_price), '0', 2);
            $lines[] = [
                'service_id' => $service->id,
                'base' => $line,
                'duration_minutes' => (int) $service->duration_minutes,
            ];
            $subtotal = bcadd($subtotal, $line, 2);
        }

        $surcharge = '0.00';
        if ($mode === DeliveryMode::Home) {
            $pct = (string) $this->settings->get('home_surcharge_pct', config('clinic.home_surcharge_pct'));
            // bcmath truncates at scale 2 (intentional): never over-charges
            // the patient; max sub-cent effect.
            $surcharge = bcdiv(bcmul($subtotal, $pct, 4), '100', 2);
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'surcharge' => $surcharge,
            'total' => bcadd($subtotal, $surcharge, 2),
        ];
    }
}
