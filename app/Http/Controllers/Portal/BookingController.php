<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Loyalty\Services\LoyaltyService;
use App\Domain\Settings\Services\SettingService;
use App\Enums\DeliveryMode;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\DoctorServicePivot;
use App\Models\HomeServiceCoverageArea;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function create(Request $request): Response
    {
        $doctorRows = DoctorProfile::where('is_bookable', true)
            ->with([
                'user:id,name',
                'services' => fn ($q) => $q->where('is_active', true)
                    ->orderBy('display_order')
                    ->orderBy('id'),
            ])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        /** @var list<array{id:int,name:string,services:list<array{id:int,name:string,base_price:string,price_override:string|null,duration_minutes:int,home_service_enabled:bool,loyalty_enabled:bool,loyalty_redemption_points:int|null}>}> $doctors */
        $doctors = [];
        foreach ($doctorRows as $d) {
            /** @var DoctorProfile $d */
            $services = [];
            foreach ($d->services as $s) {
                /** @var Service $s */
                /** @var DoctorServicePivot $pivot */
                $pivot = $s->pivot; // @phpstan-ignore-line  (Larastan does not propagate the BelongsToMany TPivotModel through this collection-iteration pattern)
                $services[] = [
                    'id' => $s->id,
                    'name' => $s->name,
                    'base_price' => $s->base_price,
                    'price_override' => $pivot->price_override,
                    'duration_minutes' => $s->duration_minutes,
                    'home_service_enabled' => $s->home_service_enabled,
                    'loyalty_enabled' => (bool) $s->loyalty_enabled,
                    'loyalty_redemption_points' => $s->loyalty_redemption_points,
                ];
            }
            /** @var User $user */
            $user = $d->user;
            $doctors[] = [
                'id' => $d->id,
                'name' => $user->name,
                'specialty' => $d->specialty,
                'team_role' => $d->team_role->value,
                'image_path' => $d->image_path,
                'services' => $services,
            ];
        }

        $coverageAreas = HomeServiceCoverageArea::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $homeSurchargePct = app(SettingService::class)->get('home_surcharge_pct', config('clinic.home_surcharge_pct'));

        $loyaltyBalance = $request->user()
            ? app(LoyaltyService::class)->balance($request->user())
            : 0;

        return Inertia::render('Portal/Booking/Create', [
            'doctors' => $doctors,
            'coverageAreas' => $coverageAreas,
            'homeSurchargePct' => $homeSurchargePct,
            'loyaltyBalance' => $loyaltyBalance,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'doctor' => ['required', 'exists:doctor_profiles,id'],
            'service' => ['required', 'exists:services,id'],
            'start' => ['required', 'date'],
            'delivery_mode' => ['required', 'in:center,home'],
            'payment_method' => ['sometimes', 'string', 'in:cash,loyalty_points'],
        ];

        if ($request->input('delivery_mode') === 'home') {
            $rules['coverage_area_id'] = ['required', 'exists:home_service_coverage_areas,id'];
            $rules['address_text'] = ['required', 'string', 'max:1000'];
            $rules['location_note'] = ['nullable', 'string', 'max:500'];
        }

        $v = $request->validate($rules);

        if (isset($v['coverage_area_id'])) {
            $areaActive = HomeServiceCoverageArea::where('id', $v['coverage_area_id'])
                ->where('is_active', true)
                ->exists();
            if (! $areaActive) {
                return back()->withErrors(['coverage_area_id' => 'منطقة التغطية غير نشطة.']);
            }
        }

        $data = new BookingData(
            customerId: $request->user()->id,
            doctorProfileId: (int) $v['doctor'],
            serviceId: (int) $v['service'],
            startAt: CarbonImmutable::parse($v['start']),
            deliveryMode: DeliveryMode::from($v['delivery_mode']),
            createdByRole: UserRole::Customer,
            coverageAreaId: isset($v['coverage_area_id']) ? (int) $v['coverage_area_id'] : null,
            addressText: $v['address_text'] ?? null,
            locationNote: $v['location_note'] ?? null,
            paymentMethod: PaymentMethod::from($v['payment_method'] ?? 'cash'),
        );

        try {
            app(BookingService::class)->book($data);
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        } catch (InvalidBookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('portal.appointments.index')->with('success', 'تم إنشاء الحجز، سيتم تأكيده قريبًا.');
    }
}
