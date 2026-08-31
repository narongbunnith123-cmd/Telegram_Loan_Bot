<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\PaymentProof;
use App\Models\TelegramGroup;
use App\Models\Reminder;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $tenant = auth()->user()->tenant;

        $stats = [
            'total_groups'      => TelegramGroup::count(),
            'active_groups'     => TelegramGroup::where('status', 'active')->count(),
            'total_loans'       => Loan::count(),
            'active_loans'      => Loan::whereIn('status', ['active', 'overdue'])->count(),
            'overdue_loans'     => Loan::where('status', 'overdue')->count(),
            'unpaid_balance'    => Loan::whereIn('status', ['active', 'overdue'])->sum('balance'),
            'collected_payments' => \App\Models\Payment::where('status', 'approved')->sum('amount'),
        ];

        $overdueLoans = Loan::with(['borrower', 'group'])
            ->where('status', 'overdue')
            ->orderByDesc('due_date')
            ->take(10)
            ->get();

        $pendingProofs = PaymentProof::with(['payment.loan.borrower'])
            ->whereHas('payment', fn($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $todayReminders = Reminder::whereDate('scheduled_at', today())->count();
        $failedReminders = Reminder::whereDate('scheduled_at', today())
            ->where('status', 'failed')
            ->count();

        return view('dashboard', compact(
            'stats', 'overdueLoans', 'pendingProofs',
            'todayReminders', 'failedReminders'
        ));
    }
}
