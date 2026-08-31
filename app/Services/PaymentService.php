<?php

namespace App\Services;

use App\Models\DailyInterestTracker;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use App\Jobs\Telegram\SendPaymentConfirmationJob;
use App\Services\Payment\PaymentReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private InstallmentService $installmentService,
        private PaymentReferenceGenerator $referenceGenerator,
    ) {
    }

    /**
     * Record a payment against a loan.
     * Single source of truth — used by both Web dashboard and Telegram bot.
     *
     * @param Loan        $loan      The loan to pay against
     * @param array       $data      Payment data: amount, type, method, notes
     * @param User|null   $approver  The admin approving (null for pending payments)
     * @param bool        $autoApprove  If true, payment is immediately approved (web dashboard)
     * @return Payment
     */
    public function recordPayment(
        Loan $loan,
        array $data,
        ?User $approver = null,
        bool $autoApprove = true,
    ): Payment {
        return DB::transaction(function () use ($loan, $data, $approver, $autoApprove) {
            $amount = (float) $data['amount'];
            $penaltyPaid = 0;
            $installmentId = null;

            // Generate reference code if not provided (for webhook payments)
            $referenceCode = $data['reference_code'] ?? $this->referenceGenerator->generate($loan);

            if ($autoApprove) {
                // Apply payment immediately to loan balance
                $allocation = $this->applyToLoan($loan, $amount);
                $penaltyPaid = $allocation['penalty_paid'];
                $installmentId = $allocation['installment_id'];
            }

            $payment = Payment::create([
                'tenant_id' => $loan->tenant_id,
                'loan_id' => $loan->id,
                'installment_id' => $installmentId,
                'amount' => $amount,
                'penalty_paid' => $penaltyPaid,
                'reference_code' => $referenceCode, // ← Auto-generated
                'type' => $data['type'] ?? 'partial',
                'method' => $data['method'] ?? null,
                'status' => $autoApprove ? 'approved' : 'pending',
                'notes' => $data['notes'] ?? null,
                'approved_by' => $autoApprove ? $approver?->id : null,
                'approved_at' => $autoApprove ? now() : null,
            ]);

            if ($autoApprove) {
                $this->postApprovalActions($loan, $payment);
            }

            // Log activity
            activity()
                ->performedOn($payment)
                ->causedBy($approver)
                ->withProperties(['amount' => $amount, 'loan_id' => $loan->id])
                ->log($autoApprove ? 'Payment recorded and approved' : 'Payment recorded (pending)');

            return $payment;
        });
    }

    /**
     * Approve a pending payment.
     * Single source of truth — used by both Web and Telegram approve flows.
     *
     * @param Payment   $payment   The payment to approve
     * @param User|null $approver  The admin approving
     * @return void
     */
    public function approvePayment(Payment $payment, ?User $approver = null): void
    {
        if ($payment->status !== 'pending') {
            throw new \InvalidArgumentException("Payment #{$payment->id} is already {$payment->status}.");
        }

        DB::transaction(function () use ($payment, $approver) {
            $loan = $payment->loan;

            // Apply payment to loan balance
            $allocation = $this->applyToLoan($loan, (float) $payment->amount);

            $payment->update([
                'status' => 'approved',
                'approved_by' => $approver?->id,
                'approved_at' => now(),
                'installment_id' => $allocation['installment_id'],
                'penalty_paid' => $allocation['penalty_paid'],
            ]);

            $this->postApprovalActions($loan, $payment);

            activity()
                ->performedOn($payment)
                ->causedBy($approver)
                ->withProperties([
                    'amount' => $payment->amount,
                    'new_balance' => $loan->fresh()->balance,
                ])
                ->log('Payment approved');
        });
    }

    /**
     * Reject a pending payment.
     *
     * @param Payment   $payment  The payment to reject
     * @param User|null $rejector The admin rejecting
     * @return void
     */
    public function rejectPayment(Payment $payment, ?User $rejector = null): void
    {
        if ($payment->status !== 'pending') {
            throw new \InvalidArgumentException("Payment #{$payment->id} is already {$payment->status}.");
        }

        $payment->update(['status' => 'rejected']);

        activity()
            ->performedOn($payment)
            ->causedBy($rejector)
            ->log('Payment rejected');
    }

    /**
     * Apply a payment amount to the loan balance.
     * Handles both lump-sum and installment loans.
     *
     * @return array{penalty_paid: float, installment_id: int|null}
     */
    private function applyToLoan(Loan $loan, float $amount): array
    {
        $penaltyPaid = 0;
        $installmentId = null;

        if ($loan->isInstallmentLoan()) {
            $installment = $this->installmentService->getNextDue($loan);

            if ($installment) {
                $installmentId = $installment->id;
                $result = $this->installmentService->applyPayment($installment, $amount);
                $penaltyPaid = $result['penalty_allocated'];

                // Carry forward excess to next installments
                if ($result['excess'] > 0) {
                    $this->installmentService->carryForwardCredit($loan, $result['excess']);
                }

                // Sync overall loan balance from installments
                $this->installmentService->syncLoanBalance($loan);

                // Auto-complete if all installments are paid
                $loan->fresh()->checkCompleted();
            }
        } else {
            // Lump-sum: payment reduces principal directly
            $loan->remaining_principal = max(0, $loan->remaining_principal - $amount);
            $loan->save();
            $loan->recalculateBalance();

            // Check if fully paid
            $loan->refresh();
            if ($loan->remaining_principal <= 0 && $loan->balance <= 0) {
                $loan->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }

        return [
            'penalty_paid' => $penaltyPaid,
            'installment_id' => $installmentId,
        ];
    }

    /**
     * Actions to perform after a payment is approved.
     * - Send Telegram confirmation
     * - Mark daily interest tracker as paid
     */
    private function postApprovalActions(Loan $loan, Payment $payment): void
    {
        // Send Telegram payment confirmation to the group
        try {
            SendPaymentConfirmationJob::dispatch($payment)->onQueue('telegram');
        } catch (\Exception $e) {
            Log::warning("Failed to dispatch payment confirmation for payment #{$payment->id}: {$e->getMessage()}");
        }

        // Mark today's interest tracker as paid (stops further reminders)
        $todayTracker = DailyInterestTracker::where('loan_id', $loan->id)
            ->where('date', today())
            ->where('is_paid', false)
            ->first();

        if ($todayTracker) {
            $todayTracker->markPaid($payment->id);
        }
    }

    /**
     * Store a payment proof file.
     *
     * @param Payment $payment
     * @param \Illuminate\Http\UploadedFile $file
     * @param User|null $uploader
     * @return PaymentProof
     */
    public function storeProof(Payment $payment, $file, ?User $uploader = null): PaymentProof
    {
        $path = $file->store('payment-proofs', 'public');

        return PaymentProof::create([
            'payment_id' => $payment->id,
            'uploaded_by' => $uploader?->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => $payment->status === 'approved' ? 'approved' : 'pending',
        ]);
    }

    /**
     * Create a payment request for webhook payment.
     * This creates a pending payment with a reference code that the user
     * will use when making payment through their bank/payment gateway.
     *
     * @param Loan $loan
     * @param float $amount
     * @param array $options Additional options (type, method, notes)
     * @return Payment
     */
    public function createPaymentRequest(Loan $loan, float $amount, array $options = []): Payment
    {
        $referenceCode = $this->referenceGenerator->generate($loan);

        $payment = Payment::create([
            'tenant_id' => $loan->tenant_id,
            'loan_id' => $loan->id,
            'amount' => $amount,
            'reference_code' => $referenceCode,
            'type' => $options['type'] ?? 'partial',
            'method' => $options['method'] ?? null,
            'status' => 'pending', // Always pending for webhook payments
            'notes' => $options['notes'] ?? null,
        ]);

        // Log activity
        activity()
            ->performedOn($payment)
            ->withProperties([
                'amount' => $amount,
                'loan_id' => $loan->id,
                'reference_code' => $referenceCode,
            ])
            ->log('Payment request created for webhook payment');

        return $payment;
    }
}
