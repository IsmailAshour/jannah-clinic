<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Domain\Payment\Services\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', 'submitted');
        $q = (string) $request->input('q', '');

        $query = Payment::query()
            ->with([
                'appointment.customer:id,name,phone,email',
                'appointment.service:id,name',
                'appointment.doctor.user:id,name',
            ])
            ->orderByDesc('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->whereHas(
                'appointment.customer',
                fn ($qq) => $qq->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like),
            );
        }

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $payment->load([
            'appointment.customer:id,name,phone,email',
            'appointment.service:id,name',
            'appointment.doctor.user:id,name',
            'receipts.uploader:id,name',
            'receipts.rejector:id,name',
            'verifier:id,name',
            'refunder:id,name',
        ]);

        return Inertia::render('Admin/Payments/Show', [
            'payment' => $payment,
        ]);
    }

    public function verify(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        try {
            $service->verify($payment, $request->user());
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم تحقّق الإيصال.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            $service->reject($payment, $request->user(), $data['reason']);
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم رفض الإيصال.');
    }

    public function markRefundPending(Payment $payment, PaymentService $service): RedirectResponse
    {
        try {
            $service->markRefundPending($payment);
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم وَسم الدفعة للاسترداد.');
    }

    public function markRefunded(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:255']]);
        try {
            $service->markRefunded($payment, $request->user(), $data['reference'] ?? null);
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم تسجيل تنفيذ الاسترداد.');
    }

    public function receiptFile(Payment $payment, PaymentReceipt $receipt): HttpResponse
    {
        abort_unless($receipt->payment_id === $payment->id, 404);

        return Storage::disk('local')->response($receipt->file_path);
    }
}
