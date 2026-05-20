<?php

namespace App\Domain\Notification\Services;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\AppointmentChanged;
use App\Notifications\MedicalRecordChanged;
use App\Notifications\PaymentChanged;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
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
            $r->notify(new AppointmentChanged($payload));
        }
    }

    public function appointmentConfirmed(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'تمّ تأكيد موعدك',
            'body' => "تمّ تأكيد موعدك بتاريخ {$a->start_at->isoFormat('D MMM YYYY HH:mm')}.",
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function appointmentRejected(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'نأسف، تعذّر تأكيد موعدك',
            'body' => 'تواصل مع العيادة لإعادة الجدولة.',
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function appointmentCancelledByStaff(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'تمّ إلغاء موعدك',
            'body' => "تمّ إلغاء موعدك بتاريخ {$a->start_at->isoFormat('D MMM HH:mm')}.",
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function appointmentRescheduledForCustomer(Appointment $newAppt): void
    {
        $newAppt->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'إعادة جدولة موعد',
            'body' => "تمّ نقل موعدك إلى {$newAppt->start_at->isoFormat('D MMM YYYY HH:mm')}.",
            'action_url' => "/portal/appointments/{$newAppt->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $newAppt->id,
        ]));
    }

    public function appointmentCompleted(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'اكتمل موعدك',
            'body' => 'يمكنك مراجعة سجلك الطبي لاحقًا.',
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
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
            $r->notify(new PaymentChanged($payload));
        }
    }

    public function paymentApproved(Payment $p): void
    {
        $p->appointment->customer->notify(new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'تمّ تأكيد دفعتك',
            'body' => 'تمّت مراجعة إيصالك وقبوله.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]));
    }

    public function paymentRejected(Payment $p): void
    {
        $p->appointment->customer->notify(new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'الإيصال مرفوض',
            'body' => 'أعد رفع الإيصال — تفاصيل الرفض داخل الصفحة.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]));
    }

    public function paymentRefunded(Payment $p): void
    {
        $p->appointment->customer->notify(new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'تمّ تنفيذ الاسترداد',
            'body' => 'تمّ إعادة المبلغ إلى حسابك.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]));
    }

    public function medicalEntryCreated(MedicalEntry $e): void
    {
        $e->appointment->customer->notify(new MedicalRecordChanged([
            'category' => NotificationCategory::Medical->value,
            'title' => 'أضاف الطبيب ملاحظة على زيارتك',
            'body' => 'افتح سجلك الطبي لمراجعة الخلاصة الجديدة.',
            'action_url' => "/portal/medical-record/entries/{$e->id}",
            'subject_type' => MedicalEntry::class,
            'subject_id' => $e->id,
        ]));
    }

    public function prescriptionAdded(Prescription $p): void
    {
        $entry = $p->entry;
        $entry->appointment->customer->notify(new MedicalRecordChanged([
            'category' => NotificationCategory::Medical->value,
            'title' => 'تمّ إضافة وصفة جديدة',
            'body' => 'افتح سجلك الطبي لرؤية الوصفة.',
            'action_url' => "/portal/medical-record/entries/{$entry->id}",
            'subject_type' => MedicalEntry::class,
            'subject_id' => $entry->id,
        ]));
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
