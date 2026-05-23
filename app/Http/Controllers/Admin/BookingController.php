<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\AuthService;
use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Domain\Booking\Services\BookingService;
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
use App\Support\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function create(): Response
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

        /** @var list<array{id:int,name:string,services:list<array{id:int,name:string,base_price:string,price_override:string|null,duration_minutes:int,home_service_enabled:bool,online_service_enabled:bool,loyalty_enabled:bool,loyalty_redemption_points:int|null}>}> $doctors */
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
                    'online_service_enabled' => $s->online_service_enabled,
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

        // Customers no longer bulk-loaded — the wizard's picker uses an
        // on-demand AJAX search against /admin/customers/search.
        return Inertia::render('Admin/Booking/Create', [
            'doctors' => $doctors,
            'coverageAreas' => $coverageAreas,
            'homeSurchargePct' => $homeSurchargePct,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // The wizard sends either 'service' (single, legacy) or 'services[]'
        // (multi). Normalise BEFORE validating so both shapes use the same
        // rule set.
        if ($request->has('service') && ! $request->has('services')) {
            $request->merge(['services' => [$request->input('service')]]);
        }

        $rules = [
            'doctor' => ['required', 'exists:doctor_profiles,id'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', 'distinct', 'exists:services,id'],
            'start' => ['required', 'date'],
            'delivery_mode' => ['required', 'in:center,home,online'],
            'payment_method' => ['sometimes', 'string', 'in:cash,loyalty_points'],
            'customer_id' => ['nullable', 'integer'],
            'new_customer' => ['nullable', 'array'],
            'new_customer.name' => ['required_with:new_customer', 'string', 'max:255'],
            'new_customer.email' => ['nullable', 'email', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($request->input('delivery_mode') === 'home') {
            $rules['coverage_area_id'] = ['required', 'exists:home_service_coverage_areas,id'];
            $rules['address_text'] = ['required', 'string', 'max:1000'];
            $rules['location_note'] = ['nullable', 'string', 'max:500'];
            $rules['lat'] = ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'];
            $rules['lng'] = ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'];
        }

        if ($request->input('delivery_mode') === 'online') {
            $rules['whatsapp_phone'] = ['required', 'string', 'min:8', 'max:32'];
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

        // Resolve the customer
        $customerId = null;

        if (! empty($v['new_customer'])) {
            /** @var array{name:string,email?:string|null,phone?:string|null} $nc */
            $nc = $v['new_customer'];
            if (empty($nc['email']) && empty($nc['phone'])) {
                return back()->withErrors(['new_customer' => 'يجب توفير بريد إلكتروني أو رقم هاتف للعميل الجديد.']);
            }
            $newCustomer = app(AuthService::class)->registerCustomer([
                'name' => $nc['name'],
                'email' => $nc['email'] ?? null,
                'phone' => $nc['phone'] ?? null,
                'password' => Str::password(16),
            ]);
            $customerId = $newCustomer->id;
        } elseif (! empty($v['customer_id'])) {
            $customerExists = User::where('id', $v['customer_id'])
                ->where('role', UserRole::Customer)
                ->exists();
            if (! $customerExists) {
                return back()->withErrors(['customer_id' => 'المستخدم المحدد غير موجود أو ليس عميلاً.']);
            }
            $customerId = (int) $v['customer_id'];
        } else {
            return back()->withErrors(['customer_id' => 'يجب تحديد عميل أو إنشاء عميل جديد.']);
        }

        $data = new BookingData(
            customerId: $customerId,
            doctorProfileId: (int) $v['doctor'],
            serviceIds: array_map('intval', $v['services']),
            startAt: CarbonImmutable::parse($v['start']),
            deliveryMode: DeliveryMode::from($v['delivery_mode']),
            createdByRole: $request->user()->role,
            coverageAreaId: isset($v['coverage_area_id']) ? (int) $v['coverage_area_id'] : null,
            addressText: $v['address_text'] ?? null,
            locationNote: $v['location_note'] ?? null,
            lat: isset($v['lat']) ? (float) $v['lat'] : null,
            lng: isset($v['lng']) ? (float) $v['lng'] : null,
            whatsappPhone: isset($v['whatsapp_phone']) ? PhoneNormalizer::toE164($v['whatsapp_phone']) : null,
            paymentMethod: PaymentMethod::from($v['payment_method'] ?? 'cash'),
        );

        try {
            app(BookingService::class)->book($data);
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        } catch (InvalidBookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('admin.appointments.index')->with('success', 'تم إنشاء الحجز.');
    }
}
