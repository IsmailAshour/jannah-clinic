<?php

namespace App\Domain\Notification\Services;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\LoyaltyLedger;
use App\Models\MedicalEntry;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\AppointmentChanged;
use App\Notifications\LoyaltyChanged;
use App\Notifications\MedicalRecordChanged;
use App\Notifications\PaymentChanged;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Dispatch a notification with isolated error handling. A failure here
     * (null relation chain, channel resolution error, deadlock on the
     * notifications table) is logged but does NOT propagate to the caller,
     * so a notification bug cannot roll back a payment or appointment write.
     */
    private function dispatch(?User $recipient, Notification $notification, string $context): void
    {
        if ($recipient === null) {
            Log::warning("NotificationService::{$context} skipped — recipient is null");

            return;
        }
        try {
            $recipient->notify($notification);
        } catch (\Throwable $e) {
            Log::warning("NotificationService::{$context} failed", [
                'recipient_id' => $recipient->id,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function bookingRequested(Appointment $a): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::Manager, UserRole::Receptionist])
            ->where('is_active', true)
            ->get();
        $payload = [
            'category' => NotificationCategory::Appointment->value,
            'title' => 'طلب حجز جديد',
            'body' => "طلب حجز جديد من {$a->customer->name} — بانتظار التأكيد.",
            'action_url' => "/admin/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ];
        foreach ($recipients as $r) {
            $this->dispatch($r, new AppointmentChanged($payload), 'bookingRequested');
        }
    }

    public function appointmentConfirmed(Appointment $a): void
    {
        $this->dispatch($a->customer, new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'تمّ تأكيد موعدك',
            'body' => "تمّ تأكيد موعدك بتاريخ {$a->start_at->isoFormat('D MMM YYYY HH:mm')}.",
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]), 'appointmentConfirmed');
    }

    public function appointmentRejected(Appointment $a): void
    {
        $this->dispatch($a->customer, new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'نأسف، تعذّر تأكيد موعدك',
            'body' => 'تواصل مع العيادة لإعادة الجدولة.',
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]), 'appointmentRejected');
    }

    public function appointmentCancelledByCustomer(Appointment $a): void
    {
        $recipients = User::query()
            ->where('role', UserRole::Manager)
            ->where('is_active', true)
            ->get();
        $payload = [
            'category' => NotificationCategory::Appointment->value,
            'title' => 'إلغاء موعد من العميل',
            'body' => "ألغى {$a->customer->name} الموعد بتاريخ {$a->start_at->isoFormat('D MMM HH:mm')}.",
            'action_url' => "/admin/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ];
        foreach ($recipients as $r) {
            $this->dispatch($r, new AppointmentChanged($payload), 'appointmentCancelledByCustomer');
        }
        if ($a->doctor && $a->doctor->user) {
            $this->dispatch($a->doctor->user, new AppointmentChanged($payload), 'appointmentCancelledByCustomer');
        }
    }

    public function appointmentCancelledByStaff(Appointment $a): void
    {
        $this->dispatch($a->customer, new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'تمّ إلغاء موعدك',
            'body' => "تمّ إلغاء موعدك بتاريخ {$a->start_at->isoFormat('D MMM HH:mm')}.",
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]), 'appointmentCancelledByStaff');
    }

    public function appointmentRescheduledForStaff(Appointment $newAppt): void
    {
        $recipients = User::query()
            ->where('role', UserRole::Manager)
            ->where('is_active', true)
            ->get();
        $payload = [
            'category' => NotificationCategory::Appointment->value,
            'title' => 'إعادة جدولة من العميل',
            'body' => "غيّر {$newAppt->customer->name} الموعد إلى {$newAppt->start_at->isoFormat('D MMM HH:mm')}.",
            'action_url' => "/admin/appointments/{$newAppt->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $newAppt->id,
        ];
        foreach ($recipients as $r) {
            $this->dispatch($r, new AppointmentChanged($payload), 'appointmentRescheduledForStaff');
        }
        if ($newAppt->doctor && $newAppt->doctor->user) {
            $this->dispatch($newAppt->doctor->user, new AppointmentChanged($payload), 'appointmentRescheduledForStaff');
        }
    }

    public function appointmentRescheduledForCustomer(Appointment $newAppt): void
    {
        $this->dispatch($newAppt->customer, new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'إعادة جدولة موعد',
            'body' => "تمّ نقل موعدك إلى {$newAppt->start_at->isoFormat('D MMM YYYY HH:mm')}.",
            'action_url' => "/portal/appointments/{$newAppt->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $newAppt->id,
        ]), 'appointmentRescheduledForCustomer');
    }

    public function appointmentCompleted(Appointment $a): void
    {
        $this->dispatch($a->customer, new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'اكتمل موعدك',
            'body' => 'يمكنك مراجعة سجلك الطبي لاحقًا.',
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]), 'appointmentCompleted');
    }

    public function paymentReceiptUploaded(Payment $p): void
    {
        $recipients = User::query()->where('role', UserRole::Manager)->where('is_active', true)->get();
        $payload = [
            'category' => NotificationCategory::Payment->value,
            'title' => 'إيصال جديد بانتظار المراجعة',
            'body' => "رُفع إيصال على الموعد رقم {$p->appointment_id}.",
            'action_url' => "/admin/payments/{$p->id}",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ];
        foreach ($recipients as $r) {
            $this->dispatch($r, new PaymentChanged($payload), 'paymentReceiptUploaded');
        }
    }

    public function paymentApproved(Payment $p): void
    {
        $this->dispatch($p->appointment->customer, new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'تمّ تأكيد دفعتك',
            'body' => 'تمّت مراجعة إيصالك وقبوله.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]), 'paymentApproved');
    }

    public function paymentRejected(Payment $p): void
    {
        $this->dispatch($p->appointment->customer, new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'الإيصال مرفوض',
            'body' => 'أعد رفع الإيصال — تفاصيل الرفض داخل الصفحة.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]), 'paymentRejected');
    }

    public function paymentRefunded(Payment $p): void
    {
        $this->dispatch($p->appointment->customer, new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'تمّ تنفيذ الاسترداد',
            'body' => 'تمّ إعادة المبلغ إلى حسابك.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]), 'paymentRefunded');
    }

    public function medicalEntryCreated(MedicalEntry $e): void
    {
        $this->dispatch($e->appointment->customer, new MedicalRecordChanged([
            'category' => NotificationCategory::Medical->value,
            'title' => 'أضاف الطبيب ملاحظة على زيارتك',
            'body' => 'افتح سجلك الطبي لمراجعة الخلاصة الجديدة.',
            'action_url' => "/portal/medical-record/entries/{$e->id}",
            'subject_type' => MedicalEntry::class,
            'subject_id' => $e->id,
        ]), 'medicalEntryCreated');
    }

    public function prescriptionAdded(Prescription $p): void
    {
        $entry = $p->entry;
        $this->dispatch($entry->appointment->customer, new MedicalRecordChanged([
            'category' => NotificationCategory::Medical->value,
            'title' => 'تمّ إضافة وصفة جديدة',
            'body' => 'افتح سجلك الطبي لرؤية الوصفة.',
            'action_url' => "/portal/medical-record/entries/{$entry->id}",
            'subject_type' => MedicalEntry::class,
            'subject_id' => $entry->id,
        ]), 'prescriptionAdded');
    }

    public function loyaltyPointsEarned(LoyaltyLedger $entry): void
    {
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "+{$entry->points_delta} نقطة من زيارتك",
            'body' => "رصيدك الآن {$entry->balance_after} نقطة.",
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsEarned');
    }

    public function loyaltyPointsRedeemed(LoyaltyLedger $entry): void
    {
        $abs = abs($entry->points_delta);
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "{$abs} نقطة استُبدلت بحجز",
            'body' => "رصيدك الآن {$entry->balance_after} نقطة.",
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsRedeemed');
    }

    public function loyaltyPointsAdjusted(LoyaltyLedger $entry): void
    {
        $sign = $entry->points_delta > 0 ? '+' : '';
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "عدّل الطاقم رصيدك ({$sign}{$entry->points_delta})",
            'body' => $entry->notes ?: 'بدون سبب مذكور.',
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsAdjusted');
    }

    public function loyaltyPointsReversed(LoyaltyLedger $entry): void
    {
        $sign = $entry->points_delta > 0 ? '+' : '';
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "{$sign}{$entry->points_delta} نقطة بعد إلغاء/استرداد",
            'body' => "رصيدك الآن {$entry->balance_after} نقطة.",
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsReversed');
    }

    public function markAsRead(DatabaseNotification $n, User $user): void
    {
        if ((int) $n->notifiable_id !== $user->id || $n->notifiable_type !== User::class) {
            abort(403);
        }
        if ($n->read_at === null) {
            $n->markAsRead();
        }
    }

    public function markAllAsRead(User $user): int
    {
        return (int) $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
