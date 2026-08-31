<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Console\Command;

class CreateTestPayment extends Command
{
    protected $signature = 'payment:create-test {loan_id} {amount} {reference}';
    protected $description = 'Create a test payment for webhook testing';

    public function handle()
    {
        $loanId = $this->argument('loan_id');
        $amount = $this->argument('amount');
        $reference = $this->argument('reference');

        $loan = Loan::find($loanId);
        
        if (!$loan) {
            $this->error("Loan {$loanId} not found!");
            $this->info("Available loans: " . Loan::pluck('id')->implode(', '));
            return 1;
        }

        $payment = Payment::create([
            'tenant_id' => $loan->tenant_id,
            'loan_id' => $loan->id,
            'amount' => $amount,
            'reference_code' => $reference,
            'status' => 'pending',
            'type' => 'partial',
        ]);

        $this->info("Payment created successfully!");
        $this->info("ID: {$payment->id}");
        $this->info("Reference: {$payment->reference_code}");
        $this->info("Amount: {$payment->amount}");
        $this->info("Status: {$payment->status}");

        return 0;
    }
}
