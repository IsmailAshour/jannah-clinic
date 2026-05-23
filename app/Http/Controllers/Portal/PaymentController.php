<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /**
     * Show the payment page for an appointment the customer owns. Surface
     * isolation already restricts the route group to role:customer; this
     * additional ownership check ensures a customer cannot view another
     * customer's payment by ID.
     */
    public function show(Request $request, Appointment $appointment, SettingService $settings): Response
    {
        abort_unless($appointment->customer_id === $request->user()->id, 403);
        $payment = $appointment->payment()->with(['receipts' => fn ($q) => $q->orderByDesc('id')])->firstOrFail();

        return Inertia::render('Portal/Payments/Show', [
            'appointment' => $appointment->load('services:id,name', 'doctor.user:id,name'),
            'payment' => $payment,
            'bank' => [
                'name' => $settings->get('bank_name', config('clinic.bank_name', '')),
                'account_holder' => $settings->get('bank_account_holder', config('clinic.bank_account_holder', '')),
                'iban' => $settings->get('bank_iban', config('clinic.bank_iban', '')),
                'account_number' => $settings->get('bank_account_number', config('clinic.bank_account_number', '')),
            ],
        ]);
    }

    public function upload(Request $request, Appointment $appointment, PaymentService $service): RedirectResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id, 403);
        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        /** @var Payment $payment */
        $payment = $appointment->payment()->firstOrFail();
        try {
            $service->uploadReceipt($payment, $request->file('receipt'), $request->user());
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('success', 'تم رفع الإيصال — بانتظار التحقّق.');
    }

    /**
     * Stream a receipt file that belongs to the authenticated customer's own
     * appointment. Customers may only download their own receipts; staff use
     * the admin route. Three ownership boundaries are checked here:
     *   1. appointment.customer_id === current user (route group already
     *      restricts to role:customer, this catches forged appointment IDs)
     *   2. receipt.payment_id === the appointment's payment.id (prevents
     *      mixing receipts from another appointment via URL manipulation)
     */
    public function receiptFile(Request $request, Appointment $appointment, PaymentReceipt $receipt): StreamedResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id, 403);
        /** @var Payment $payment */
        $payment = $appointment->payment()->firstOrFail();
        abort_unless($receipt->payment_id === $payment->id, 404);

        return Storage::disk('local')->response($receipt->file_path);
    }
}
