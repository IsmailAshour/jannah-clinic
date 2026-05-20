<?php

namespace App\Domain\Loyalty\Services;

use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function awardForPayment(Payment $payment): void
    {
        $exists = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::EarnedFromPayment->value)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();
        if ($exists) {
            return;
        }
        $points = (int) floor((float) $payment->amount);
        if ($points <= 0) {
            return;
        }
        $customer = $payment->appointment->customer;
        $this->writeEntry($customer, $points, LoyaltyReason::EarnedFromPayment, $payment);
    }

    public function clawbackForRefund(Payment $payment): void
    {
        $earned = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::EarnedFromPayment->value)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->value('points_delta');
        if ($earned === null) {
            return;
        }
        $alreadyClawed = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::ClawbackFromRefund->value)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();
        if ($alreadyClawed) {
            return;
        }
        $customer = $payment->appointment->customer;
        $this->writeEntry($customer, -$earned, LoyaltyReason::ClawbackFromRefund, $payment);
    }

    public function redeemForAppointment(Appointment $appointment, User $customer): int
    {
        $service = $appointment->service;
        if (! $service->loyalty_enabled || $service->loyalty_redemption_points === null || $service->loyalty_redemption_points <= 0) {
            throw new InsufficientLoyaltyBalanceException('الخدمة غير متاحة للاستبدال بالنقاط.');
        }
        $cost = (int) $service->loyalty_redemption_points;
        $balance = $this->balance($customer);
        if ($balance < $cost) {
            throw new InsufficientLoyaltyBalanceException("رصيد النقاط غير كافٍ (المطلوب {$cost}، المتاح {$balance}).");
        }
        $this->writeEntry($customer, -$cost, LoyaltyReason::RedeemedForAppointment, $appointment);

        return $cost;
    }

    public function reverseRedemption(Appointment $cancelled): void
    {
        if ($cancelled->payment_method !== 'loyalty_points' || $cancelled->loyalty_points_spent === null) {
            return;
        }
        $alreadyReversed = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::RefundReversal->value)
            ->where('reference_type', Appointment::class)
            ->where('reference_id', $cancelled->id)
            ->exists();
        if ($alreadyReversed) {
            return;
        }
        $customer = $cancelled->customer;
        $this->writeEntry($customer, (int) $cancelled->loyalty_points_spent, LoyaltyReason::RefundReversal, $cancelled);
    }

    public function adjust(User $customer, int $delta, string $note, User $manager): void
    {
        if ($manager->role !== UserRole::Manager) {
            throw new AuthorizationException('فقط المدير يستطيع تعديل النقاط.');
        }
        if ($delta === 0) {
            return;
        }
        $this->writeEntry($customer, $delta, LoyaltyReason::AdjustmentByManager, null, $note, $manager);
    }

    public function balance(User $customer): int
    {
        $profile = $customer->customerProfile;
        if ($profile === null) {
            $profile = CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
        }

        return (int) $profile->loyalty_balance;
    }

    private function writeEntry(
        User $customer,
        int $delta,
        LoyaltyReason $reason,
        ?Model $reference = null,
        ?string $notes = null,
        ?User $actor = null,
    ): void {
        DB::transaction(function () use ($customer, $delta, $reason, $reference, $notes, $actor) {
            $profile = $customer->customerProfile
                ?? CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
            $newBalance = (int) $profile->loyalty_balance + $delta;
            LoyaltyLedger::create([
                'customer_id' => $customer->id,
                'points_delta' => $delta,
                'balance_after' => $newBalance,
                'reason' => $reason->value,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'actor_id' => $actor?->id,
            ]);
            $profile->update(['loyalty_balance' => $newBalance]);
        });
    }
}
