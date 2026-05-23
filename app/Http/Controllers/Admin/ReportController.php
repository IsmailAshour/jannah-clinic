<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Range presets:
     *   - month      → current calendar month (default)
     *   - last_month → previous calendar month
     *   - 3m         → last 90 days
     *   - 6m         → last 180 days
     *   - year       → current year
     *   - custom     → uses from/to query params
     *
     * Aggregations are done in PHP after a single fetch per dataset so the
     * controller stays portable across SQLite (tests) and Postgres (prod)
     * without dialect-specific DATE_TRUNC.
     */
    public function index(Request $request): Response
    {
        $range = (string) $request->input('range', 'month');
        [$from, $to, $label] = $this->resolveRange($range, $request);

        // ---- Fetch base data ----
        $appointments = Appointment::query()
            ->whereBetween('start_at', [$from, $to])
            ->with(['services:id,name', 'doctor:id,user_id', 'doctor.user:id,name', 'customer:id'])
            ->get(['id', 'customer_id', 'doctor_profile_id', 'start_at', 'status', 'delivery_mode', 'price_at_booking']);

        $payments = Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'amount', 'status', 'created_at']);

        $newCustomers = User::query()
            ->where('role', UserRole::Customer)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // ---- Top-line stats ----
        $totalRevenue = (string) $payments
            ->where('status', PaymentStatus::Paid)
            ->reduce(fn (string $carry, $p) => bcadd($carry, (string) $p->amount, 2), '0');

        $totalAppointments = $appointments->count();

        $completedCount = $appointments->where('status', AppointmentStatus::Completed)->count();
        $noShowCount = $appointments->where('status', AppointmentStatus::NoShow)->count();
        $finalisedCount = $completedCount + $noShowCount;
        $noShowRate = $finalisedCount > 0 ? round(($noShowCount / $finalisedCount) * 100, 1) : 0.0;

        // ---- Status breakdown ----
        $statusCounts = [];
        foreach (AppointmentStatus::cases() as $s) {
            $statusCounts[$s->value] = $appointments->where('status', $s)->count();
        }

        // ---- Delivery breakdown ----
        $homeCount = $appointments->where('delivery_mode', DeliveryMode::Home)->count();
        $centerCount = $appointments->where('delivery_mode', DeliveryMode::Center)->count();
        $onlineCount = $appointments->where('delivery_mode', DeliveryMode::Online)->count();

        // ---- Monthly revenue trend (within the selected range) ----
        $monthlyRevenue = [];
        $monthCursor = $from->copy()->startOfMonth();
        while ($monthCursor <= $to) {
            $key = $monthCursor->format('Y-m');
            $monthlyRevenue[$key] = ['month' => $key, 'label' => $monthCursor->isoFormat('MMM YYYY'), 'amount' => '0'];
            $monthCursor = $monthCursor->addMonth();
        }
        foreach ($payments as $p) {
            if ($p->status !== PaymentStatus::Paid) {
                continue;
            }
            $k = $p->created_at->format('Y-m');
            if (! isset($monthlyRevenue[$k])) {
                continue;
            }
            $monthlyRevenue[$k]['amount'] = bcadd((string) $monthlyRevenue[$k]['amount'], (string) $p->amount, 2);
        }
        $monthlyRevenue = array_values($monthlyRevenue);

        // ---- Top services (by booking count) ----
        // Multi-service: a single appointment with 3 services counts each
        // service once (matches "how often was X rendered"). Revenue is
        // per-line via the pivot's price_at_booking.
        $topServicesRaw = DB::table('appointment_services as as_pv')
            ->join('appointments as a', 'a.id', '=', 'as_pv.appointment_id')
            ->join('services as s', 's.id', '=', 'as_pv.service_id')
            ->whereBetween('a.start_at', [$from, $to])
            ->groupBy('as_pv.service_id', 's.name')
            ->select('as_pv.service_id as id', 's.name as name', DB::raw('count(*) as cnt'), DB::raw('sum(as_pv.price_at_booking) as revenue'))
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();
        $topServices = $topServicesRaw->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => (string) $r->name,
            'count' => (int) $r->cnt,
            'revenue' => bcadd((string) $r->revenue, '0', 2),
        ])->values()->all();

        // ---- Top team members (by booking count) ----
        $topDoctors = $appointments
            ->groupBy('doctor_profile_id')
            ->map(fn ($rows) => [
                'id' => $rows->first()->doctor_profile_id,
                'name' => $rows->first()->doctor->user->name ?? '—',
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();

        return Inertia::render('Admin/Reports/Index', [
            'range' => $range,
            'rangeLabel' => $label,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'stats' => [
                'total_revenue' => $totalRevenue,
                'total_appointments' => $totalAppointments,
                'new_customers' => $newCustomers,
                'no_show_rate' => $noShowRate,
                'completed' => $completedCount,
                'no_show' => $noShowCount,
            ],
            'monthlyRevenue' => $monthlyRevenue,
            'statusCounts' => $statusCounts,
            'deliveryBreakdown' => [
                'home' => $homeCount,
                'center' => $centerCount,
                'online' => $onlineCount,
            ],
            'topServices' => $topServices,
            'topDoctors' => $topDoctors,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveRange(string $range, Request $request): array
    {
        $now = CarbonImmutable::now();

        return match ($range) {
            'last_month' => [
                $now->subMonth()->startOfMonth(),
                $now->subMonth()->endOfMonth(),
                'الشهر الماضي',
            ],
            '3m' => [
                $now->subDays(90)->startOfDay(),
                $now->endOfDay(),
                'آخر 90 يومًا',
            ],
            '6m' => [
                $now->subDays(180)->startOfDay(),
                $now->endOfDay(),
                'آخر 180 يومًا',
            ],
            'year' => [
                $now->startOfYear(),
                $now->endOfYear(),
                (string) $now->year,
            ],
            'custom' => (function () use ($request, $now) {
                $from = $request->filled('from')
                    ? CarbonImmutable::parse((string) $request->input('from'))->startOfDay()
                    : $now->startOfMonth();
                $to = $request->filled('to')
                    ? CarbonImmutable::parse((string) $request->input('to'))->endOfDay()
                    : $now->endOfDay();

                return [$from, $to, $from->toDateString().' → '.$to->toDateString()];
            })(),
            default => [
                $now->startOfMonth(),
                $now->endOfMonth(),
                'هذا الشهر',
            ],
        };
    }
}
