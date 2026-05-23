<?php

namespace App\Domain\Payment\Services;

use App\Domain\Loyalty\Services\LoyaltyService;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function uploadReceipt(Payment $payment, UploadedFile $file, User $uploader): PaymentReceipt
    {
        if ($file->getSize() === false || $file->getSize() <= 0 || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidPaymentTransitionException('حجم الإيصال يجب أن لا يتجاوز 5 ميغابايت.');
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidPaymentTransitionException('صيغة الملف غير مدعومة. ارفع JPG أو PNG أو PDF.');
        }
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Rejected], true)) {
            throw new InvalidPaymentTransitionException("لا يمكن رفع إيصال عندما تكون الحالة {$payment->status->value}.");
        }

        $receipt = DB::transaction(function () use ($payment, $file, $uploader) {
            $ext = $file->getClientOriginalExtension() ?: 'bin';
            $name = Str::uuid()->toString().'.'.$ext;
            $path = $file->storeAs("receipts/{$payment->id}", $name, 'local');

            $receipt = PaymentReceipt::create([
                'payment_id' => $payment->id,
                'uploaded_by' => $uploader->id,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'uploaded',
            ]);

            $payment->update([
                'status' => PaymentStatus::Submitted,
                'rejection_reason' => null,
            ]);

            return $receipt;
        });
        // Notify AFTER the transaction commits: a notification failure must not
        // roll back a stored receipt or leave an orphaned file on disk.
        $this->notifications->paymentReceiptUploaded($payment);

        return $receipt;
    }

    public function verify(Payment $payment, User $manager): Payment
    {
        if ($payment->status !== PaymentStatus::Submitted) {
            throw new InvalidPaymentTransitionException("لا يمكن التحقّق إلا من إيصال قيد المراجعة (الحالة الحالية: {$payment->status->value}).");
        }

        $payment = DB::transaction(function () use ($payment, $manager) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'verified_at' => now(),
                'verified_by' => $manager->id,
                'rejection_reason' => null,
            ]);

            return $payment;
        });
        $this->notifications->paymentApproved($payment);
        // awardForPayment is self-gating now — it sums only the
        // loyalty-eligible services' line prices and writes a ledger entry
        // when the eligible subtotal > 0. Safe to call unconditionally.
        $this->loyalty->awardForPayment($payment);

        return $payment;
    }

    public function reject(Payment $payment, User $manager, string $reason): Payment
    {
        if ($payment->status !== PaymentStatus::Submitted) {
            throw new InvalidPaymentTransitionException("لا يمكن رفض إلا إيصالًا قيد المراجعة (الحالة الحالية: {$payment->status->value}).");
        }

        $payment = DB::transaction(function () use ($payment, $manager, $reason) {
            /** @var PaymentReceipt|null $latest */
            $latest = $payment->receipts()->first();
            if ($latest && $latest->status === 'uploaded') {
                $latest->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'rejected_at' => now(),
                    'rejected_by' => $manager->id,
                ]);
            }
            $payment->update([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            return $payment;
        });
        $this->notifications->paymentRejected($payment);

        return $payment;
    }

    public function markRefundPending(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw new InvalidPaymentTransitionException("لا يمكن طلب استرداد إلا لدفعة مُسدَّدة (الحالة الحالية: {$payment->status->value}).");
        }
        // Once the visit happened and was paid for, the service has been
        // rendered — there's nothing to refund. Refunds are reserved for
        // cancelled / rejected / no-show / rescheduled appointments where
        // the patient paid but never received the service.
        if ($payment->appointment->status === AppointmentStatus::Completed) {
            throw new InvalidPaymentTransitionException('لا يمكن استرداد المبلغ — الموعد اكتمل وتمّ تقديم الخدمة.');
        }
        $payment->update(['status' => PaymentStatus::RefundPending]);

        return $payment;
    }

    public function markRefunded(Payment $payment, User $manager, ?string $reference = null): Payment
    {
        if ($payment->status !== PaymentStatus::RefundPending) {
            throw new InvalidPaymentTransitionException("لا يمكن تسجيل استرداد إلا لدفعة بانتظار الاسترداد (الحالة الحالية: {$payment->status->value}).");
        }

        $payment = DB::transaction(function () use ($payment, $manager, $reference) {
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
                'refunded_by' => $manager->id,
                'refund_reference' => $reference,
            ]);

            return $payment;
        });
        $this->notifications->paymentRefunded($payment);
        $this->loyalty->clawbackForRefund($payment);

        return $payment;
    }
}
