<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            'appointment' => $appointment->load('service:id,name', 'doctor.user:id,name'),
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
}
