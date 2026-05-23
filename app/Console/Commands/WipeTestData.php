<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentPhoto;
use App\Models\MedicalAttachment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes test/seed customers + appointments + medical records + payments
 * + every file attached to them, while leaving staff (managers, doctors,
 * receptionists), doctor profiles, schedules, services, coverage areas,
 * and clinic settings untouched.
 *
 * Designed for the day a clinic goes live with real patients and wants
 * to clear out testing residue. NOT a recurring/scheduled command —
 * runs once per environment, on demand.
 */
class WipeTestData extends Command
{
    protected $signature = 'clinic:wipe-test-data {--force : Skip the interactive confirmation prompt}';

    protected $description = 'Permanently delete all customers + appointments + medical records + payments + their files. Preserves staff, doctors, services, settings.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=red;options=bold>⚠  WIPE TEST DATA — destructive operation</>');
        $this->newLine();

        // ---- Show what's about to die ----
        $counts = [
            'الأطبّاء (سيَبقَون)' => User::where('role', UserRole::Doctor)->count(),
            'المدراء (سيَبقَون)' => User::where('role', UserRole::Manager)->count(),
            'موظّفو الاستقبال (سيَبقَون)' => User::where('role', UserRole::Receptionist)->count(),
            '────────────────' => '',
            'العملاء (سيُحذَفون)' => User::where('role', UserRole::Customer)->count(),
            'المواعيد (ستُحذَف)' => Appointment::count(),
            'صور الجلسات (ستُحذَف)' => AppointmentPhoto::count(),
            'الملفّات الطبيّة (ستُحذَف)' => MedicalAttachment::count(),
            'الدفعات (ستُحذَف)' => DB::table('payments')->count(),
            'إيصالات الدفع (ستُحذَف)' => PaymentReceipt::count(),
            'سِجلّات الولاء (ستُحذَف)' => DB::table('loyalty_ledger')->count(),
            'الإشعارات (ستُحذَف)' => DB::table('notifications')->count(),
            'سِجلّ التدقيق الطبيّ (سيُحذَف)' => DB::table('medical_audit_logs')->count(),
        ];

        foreach ($counts as $label => $count) {
            $this->line(sprintf('  %s : %s', str_pad((string) $label, 32, ' '), (string) $count));
        }
        $this->newLine();

        $this->line('<fg=yellow>سيُبقَى</>: الخدمات + التصنيفات + مناطق التغطية + جداول الأطبّاء + الإعدادات + اللوغو');
        $this->newLine();

        // ---- Safety prompt ----
        if (! $this->option('force')) {
            if (! $this->confirm('هل تريد المتابعة؟ (سيُطلَب منك تأكيد إضافيّ)', false)) {
                $this->info('تمّ الإلغاء.');

                return self::SUCCESS;
            }
            $confirm = $this->ask('للتأكيد، اكتب: WIPE');
            if ($confirm !== 'WIPE') {
                $this->error('التأكيد غير صحيح. تمّ الإلغاء.');

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('بدء التنظيف...');

        // ---- 1. Collect file paths BEFORE we delete the rows ----
        $attachmentPaths = MedicalAttachment::pluck('file_path')->all();
        $photoPaths = AppointmentPhoto::pluck('file_path')->all();
        $receiptPaths = PaymentReceipt::pluck('file_path')->all();

        $this->line(sprintf(
            'ملفّات على القرص: %d مرفقات طبيّة + %d صور + %d إيصالات',
            count($attachmentPaths),
            count($photoPaths),
            count($receiptPaths),
        ));

        // ---- 2. Wipe DB in a transaction ----
        DB::transaction(function () {
            // Foreign keys are cascade-on-delete from appointments to:
            //   appointment_photos, appointment_reminders, medical_attachments,
            //   service_addresses, medical_entries (→ prescriptions),
            //   payments (→ payment_receipts).
            // Deleting all appointments cascades the lot.
            Appointment::query()->delete();

            // Tables that hang off customers/users but are NOT auto-cascaded:
            DB::table('medical_audit_logs')->delete();
            DB::table('loyalty_ledger')->delete();
            DB::table('notifications')->delete();

            // customer_profiles cascades from users (cascadeOnDelete), so deleting
            // the customer users handles it automatically.
            User::where('role', UserRole::Customer)->delete();
        });

        $this->info('  ✓ قاعدة البيانات نُظّفت.');

        // ---- 3. Delete files (idempotent — missing files are no-ops) ----
        foreach ($attachmentPaths as $p) {
            Storage::disk('local')->delete($p);
        }
        foreach ($photoPaths as $p) {
            Storage::disk('local')->delete($p);
        }
        foreach ($receiptPaths as $p) {
            Storage::disk('local')->delete($p);
        }
        $this->info('  ✓ الملفّات على القرص نُظّفت.');

        // ---- 4. Verify + summary ----
        $this->newLine();
        $this->line('<fg=green;options=bold>تمّ التنظيف بنجاح.</>');
        $this->line('الحالة بعد التنظيف:');
        $after = [
            'الأطبّاء' => User::where('role', UserRole::Doctor)->count(),
            'المدراء' => User::where('role', UserRole::Manager)->count(),
            'موظّفو الاستقبال' => User::where('role', UserRole::Receptionist)->count(),
            'العملاء' => User::where('role', UserRole::Customer)->count(),
            'المواعيد' => Appointment::count(),
        ];
        foreach ($after as $label => $count) {
            $this->line(sprintf('  %s : %s', str_pad((string) $label, 24, ' '), (string) $count));
        }

        return self::SUCCESS;
    }
}
