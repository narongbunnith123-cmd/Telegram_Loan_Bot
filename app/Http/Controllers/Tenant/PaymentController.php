<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentSession;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    public function index(Request $request)
    {
        $groups = \App\Models\TelegramGroup::where('status', 'active')->get();

        $payments = Payment::with(['loan.borrower', 'loan.group'])
            ->when($request->search, fn($q, $s) => $q->whereHas(
                'loan.borrower',
                fn($bq) =>
                $bq->where('name', 'like', "%{$s}%")
            ))
            ->when($request->group_id, fn($q, $id) => $q->whereHas(
                'loan',
                fn($lq) =>
                $lq->where('group_id', $id)
            ))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('payments.index', compact('payments', 'groups'));
    }

    public function create(Request $request)
    {
        $loan = $request->loan_id ? Loan::with(['borrower', 'group'])->find($request->loan_id) : null;
        $activeLoans = Loan::with('borrower')->whereIn('status', ['active', 'overdue'])->get();

        return view('payments.create', compact('loan', 'activeLoans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => ['required', Rule::exists('loans', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'amount' => 'required|numeric|min:0.01',
            'type' => 'nullable|in:partial,full,advance',
            'method' => 'nullable|string',
            'notes' => 'nullable|string',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:5120',
        ]);

        $loan = Loan::where('id', $validated['loan_id'])->firstOrFail();

        // Use shared PaymentService — auto-approved from web dashboard
        $payment = $this->paymentService->recordPayment(
            loan: $loan,
            data: $validated,
            approver: auth()->user(),
            autoApprove: true,
        );

        // Handle proof upload via shared service
        if ($request->hasFile('proof_file')) {
            $this->paymentService->storeProof($payment, $request->file('proof_file'), auth()->user());
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Payment recorded successfully.');
    }

    public function show(Payment $payment)
    {
        $payment->load(['loan.borrower', 'proofs']);
        return view('payments.show', compact('payment'));
    }

    public function approve(Payment $payment)
    {
        // Use shared PaymentService
        $this->paymentService->approvePayment($payment, auth()->user());
        return back()->with('success', 'Payment approved.');
    }

    public function reject(Payment $payment)
    {
        // Use shared PaymentService
        $this->paymentService->rejectPayment($payment, auth()->user());
        return back()->with('success', 'Payment rejected.');
    }

    /**
     * Initiate a payment request with webhook support
     * This creates a pending payment with a unique reference code
     * that the borrower will use when making the actual payment
     */
    public function initiatePayment(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'gateway' => 'required|in:aba,khqr,stripe,paypal,simulated',
            'notes' => 'nullable|string|max:500'
        ]);

        // Create payment request with auto-generated reference code
        $payment = $this->paymentService->createPaymentRequest(
            $loan,
            $validated['amount'],
            [
                'notes' => $validated['notes'] ?? null,
                'method' => $validated['gateway'], // Store gateway as payment method
            ]
        );

        // Set the gateway_name field directly
        $payment->gateway_name = $validated['gateway'];
        $payment->save();

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'reference_code' => $payment->reference_code,
                'amount' => $payment->amount,
                'gateway' => $payment->gateway, // This will use the accessor
                'status' => $payment->status,
                'created_at' => $payment->created_at->toIso8601String(),
                'instructions' => $this->getPaymentInstructions($payment)
            ]
        ]);
    }

    /**
     * Check payment status
     * Borrowers can poll this endpoint to see if their payment was received
     */
    public function checkPaymentStatus($paymentId)
    {
        // For test routes, we need to fetch payment without tenant scoping
        // since there's no authenticated user
        $payment = Payment::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->with('transaction')
            ->findOrFail($paymentId);

        return response()->json([
            'payment_id' => $payment->id,
            'reference_code' => $payment->reference_code,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'gateway' => $payment->gateway,
            'created_at' => $payment->created_at->toIso8601String(),
            'approved_at' => $payment->approved_at?->toIso8601String(),
            'transaction_id' => $payment->transaction_id,
            'transaction' => $payment->transaction ? [
                'gateway_transaction_id' => $payment->transaction->gateway_transaction_id,
                'processed_at' => $payment->transaction->processed_at?->toIso8601String(),
            ] : null
        ]);
    }

    /**
     * Get payment instructions based on gateway
     */
    protected function getPaymentInstructions(Payment $payment): string
    {
        $gatewayConfig = config("payment.gateways.{$payment->gateway}");
        $accountInfo = $gatewayConfig['account_info'] ?? '';

        $instructions = [
            'aba' => "Transfer \${$payment->amount} to ABA Bank account: {$accountInfo}. IMPORTANT: Use reference code '{$payment->reference_code}' in the transfer description.",
            'khqr' => "Scan the KHQR code and pay \${$payment->amount}. IMPORTANT: Include reference code '{$payment->reference_code}' in the payment description.",
            'stripe' => "Pay \${$payment->amount} via Stripe. IMPORTANT: Use reference code '{$payment->reference_code}' when making the payment.",
            'paypal' => "Send \${$payment->amount} to PayPal account: {$accountInfo}. IMPORTANT: Include reference code '{$payment->reference_code}' in the payment note.",
            'simulated' => "Test payment of \${$payment->amount}. Reference code: '{$payment->reference_code}'"
        ];

        return $instructions[$payment->gateway] ?? "Pay \${$payment->amount} using reference code: '{$payment->reference_code}'";
    }

    /**
     * Payment Sessions — Admin reconciliation dashboard.
     * Shows all payment sessions with filters and stats.
     */
    public function sessions(Request $request)
    {
        $sessions = PaymentSession::with(['borrower', 'loan', 'payment'])
            ->when($request->search, fn($q, $s) => $q->where('reference_code', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->gateway, fn($q, $g) => $q->where('gateway_name', $g))
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'active' => PaymentSession::where('status', 'pending')
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'paid_today' => PaymentSession::where('status', 'paid')
                ->whereDate('paid_at', today())
                ->count(),
            'expired_today' => PaymentSession::where('status', 'expired')
                ->whereDate('updated_at', today())
                ->count(),
            'failed' => PaymentSession::where('status', 'failed')->count(),
        ];

        return view('payments.sessions', compact('sessions', 'stats'));
    }

    /**
     * Payment Session Detail — view a single session with full data.
     */
    public function sessionDetail(PaymentSession $session)
    {
        $session->load(['borrower', 'loan', 'payment']);
        return view('payments.session-detail', compact('session'));
    }
}
