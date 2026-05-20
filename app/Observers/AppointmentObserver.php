<?php

namespace App\Observers;

use App\Domain\Payment\Services\PaymentService;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;

class AppointmentObserver
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Hybrid lifecycle (P2 spec §3): when an Appointment transitions to a
     * terminal "no-service-rendered" state (Cancelled or Rejected) and its
     * Payment is still 'paid', auto-mark the Payment as refund_pending so the
     * manager sees it in the refund queue. Other terminal states (Completed,
     * NoShow, Rescheduled) deliberately do NOT auto-refund — see spec.
     */
    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged('status')) {
            return;
        }

        $newStatus = $appointment->status;
        if ($newStatus !== AppointmentStatus::Cancelled && $newStatus !== AppointmentStatus::Rejected) {
            return;
        }

        /** @var Payment|null $payment */
        $payment = $appointment->payment()->first();
        if (! $payment || $payment->status !== PaymentStatus::Paid) {
            return;
        }

        $this->payments->markRefundPending($payment);
    }
}
