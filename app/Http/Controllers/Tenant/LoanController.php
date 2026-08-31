<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\TelegramGroup;
use App\Services\LoanService;
use App\Services\InstallmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function __construct(
        private LoanService $loanService,
        private InstallmentService $installmentService,
    ) {
    }

    public function index(Request $request)
    {
        $groups = TelegramGroup::where('status', 'active')->get();

        $loans = Loan::with(['borrower', 'group'])
            ->when($request->search, fn($q, $s) => $q->whereHas(
                'borrower',
                fn($bq) =>
                $bq->where('name', 'like', "%{$s}%")
            ))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->group_id, fn($q, $id) => $q->where('group_id', $id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loans.index', compact('loans', 'groups'));
    }

    public function create(Request $request)
    {
        $groups = TelegramGroup::where('status', 'active')->get();
        $borrowers = Borrower::where('status', 'active')->get();

        return view('loans.create', compact('groups', 'borrowers'));
    }

    public function store(Request $request)
    {
        $isInstallment = $request->loan_type === 'installment';

        $rules = [
            'group_id' => ['required', Rule::exists('telegram_groups', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'borrower_id' => ['required', Rule::exists('borrowers', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'principal' => 'required|numeric|min:1',
            'interest_type' => 'required|in:fixed,percentage',
            'interest_value' => 'required|numeric|min:0',
            'loan_date' => 'required|date',
            'loan_type' => 'required|in:lump_sum,installment',
            'notes' => 'nullable|string',
            // Penalty fields
            'penalty_type' => 'required|in:none,fixed,percentage',
            'penalty_value' => 'nullable|numeric|min:0',
            'grace_days' => 'nullable|integer|min:0|max:30',
        ];

        if ($isInstallment) {
            $rules['duration_months'] = 'required|integer|min:1|max:60';
        } else {
            $rules['due_date'] = 'nullable|date|after:loan_date';
        }

        $validated = $request->validate($rules);

        // Use shared LoanService — single source of truth
        $loan = $this->loanService->createLoan(
            tenantId: auth()->user()->tenant_id,
            data: $validated,
            createdBy: auth()->user(),
        );

        return redirect()->route('loans.show', $loan)->with('success', 'Loan created successfully.');
    }

    public function show(Loan $loan)
    {
        $loan->load([
            'borrower',
            'group',
            'payments.proofs',
            'interestLogs',
            'reminders',
            'installments.penaltyLogs',
            'installments.payments',
            'penaltyLogs',
        ]);
        return view('loans.show', compact('loan'));
    }

    public function cancel(Loan $loan)
    {
        $this->loanService->cancelLoan($loan, auth()->user());
        return back()->with('success', 'Loan cancelled.');
    }

    public function edit(Loan $loan)
    {
        $groups = TelegramGroup::where('status', 'active')->get();
        $borrowers = Borrower::where('status', 'active')->get();
        $loan->load(['borrower', 'group']);

        return view('loans.edit', compact('loan', 'groups', 'borrowers'));
    }

    public function update(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'group_id' => ['required', Rule::exists('telegram_groups', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'borrower_id' => ['required', Rule::exists('borrowers', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'principal' => 'required|numeric|min:1',
            'interest_type' => 'required|in:fixed,percentage',
            'interest_value' => 'required|numeric|min:0',
            'loan_date' => 'required|date',
            'due_date' => 'required|date|after:loan_date',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,overdue,paid,completed,defaulted,cancelled',
            'penalty_type' => 'nullable|in:none,fixed,percentage',
            'penalty_value' => 'nullable|numeric|min:0',
            'grace_days' => 'nullable|integer|min:0|max:30',
        ]);

        // Recalculate if principal changed
        if ((float) $validated['principal'] !== (float) $loan->principal) {
            // Recalculate daily_interest_rate
            if ($validated['principal'] > 0) {
                $validated['daily_interest_rate'] = $validated['interest_type'] === 'fixed'
                    ? $validated['interest_value'] / $validated['principal']
                    : $validated['interest_value'] / 100;
            }
            $totalPaid = $loan->payments()->where('status', 'approved')->sum('amount');
            $validated['remaining_principal'] = max(0, $validated['principal'] - $totalPaid);
            $validated['balance'] = max(0, $validated['remaining_principal'] + $loan->accrued_interest - $totalPaid);
        }

        $loan->update($validated);

        return redirect()->route('loans.show', $loan)->with('success', 'Loan updated successfully.');
    }

    /**
     * Waive penalties on a specific installment.
     */
    public function waivePenalty(Loan $loan, int $installmentId)
    {
        $installment = $loan->installments()->findOrFail($installmentId);

        app(\App\Services\PenaltyService::class)->waivePenalties($installment);
        app(InstallmentService::class)->syncLoanBalance($loan);

        return back()->with('success', "Penalty waived for installment #{$installment->installment_number}.");
    }
}
