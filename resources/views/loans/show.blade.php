@extends('layouts.app')
@section('title', 'Loan #' . $loan->id)
@section('page-title', 'Loan Detail')
@section('page-subtitle', 'View loan information, payments, and history.')

@push('header-actions')
    @if(in_array($loan->status, ['active', 'overdue']))
        <a href="{{ route('payments.create', ['loan_id' => $loan->id]) }}" class="ld-btn ld-btn-primary ld-btn-sm">+ Record
            Payment</a>
    @endif
    @if($loan->status !== 'cancelled' && $loan->status !== 'paid')
        <form method="POST" action="{{ route('loans.cancel', $loan) }}" onsubmit="return confirm('Cancel this loan?')">
            @csrf @method('PATCH')
            <button type="submit" class="ld-btn ld-btn-danger ld-btn-sm">Cancel Loan</button>
        </form>
    @endif
@endpush

@push('styles')
    <style>
        .loan-detail-page .ld-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #707272ff;
            margin-bottom: 18px;
        }

        .loan-detail-page .ld-breadcrumb a {
            color: #9ca3af;
            text-decoration: none;
            transition: color .15s;
        }

        .loan-detail-page .ld-breadcrumb a:hover {
            color: #4b5563;
        }

        .loan-detail-page .ld-breadcrumb-sep {
            color: #d1d5db;
        }

        .loan-detail-page .ld-breadcrumb-current {
            color: #16a34a;
            font-weight: 600;
        }

        .loan-detail-page .ld-card {
            background: #f3f2f2ff;
            border: 1px solid #bebec0ff;
            border-radius: 5px;
            padding: 26px;
            box-shadow: 0 2px 14px rgba(16, 24, 40, .04);
        }

        .loan-detail-page .ld-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            font-family: 'DM Sans', sans-serif;
        }

        .loan-detail-page .ld-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
        }

        .loan-detail-page .ld-badge-active {
            background: #eafcf1;
            color: #16a34a;
        }

        .loan-detail-page .ld-badge-overdue {
            background: #fef2f2;
            color: #dc2626;
        }

        .loan-detail-page .ld-badge-paid {
            background: #eff6ff;
            color: #2563eb;
        }

        .loan-detail-page .ld-badge-completed {
            background: #ecfdf5;
            color: #0d9488;
        }

        .loan-detail-page .ld-badge-defaulted {
            background: #fff7ed;
            color: #ea580c;
        }

        .loan-detail-page .ld-badge-pending {
            background: #fffbeb;
            color: #d97706;
        }

        .loan-detail-page .ld-badge-cancelled {
            background: #f3f4f6;
            color: #6b7280;
        }

        .loan-detail-page .ld-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            border: none;
            transition: all .15s;
            text-decoration: none;
        }

        .loan-detail-page .ld-btn-primary {
            background: #10B981;
            color: #ffffff;
        }

        .loan-detail-page .ld-btn-primary:hover {
            background: #15803d;
        }

        .loan-detail-page .ld-btn-danger {
            background: #FECACA;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .loan-detail-page .ld-btn-danger:hover {
            background: #f38a8aff;
            color: #eafcf1;
        }

        .loan-detail-page .ld-btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }

        .loan-detail-page .ld-table {
            width: 100%;
            border-collapse: collapse;
        }

        .loan-detail-page .ld-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #9ca3af;
            border-bottom: 1px solid #f0f1f3;
        }

        .loan-detail-page .ld-table td {
            padding: 16px;
            font-size: 14px;
            color: #111827;
            border-bottom: 1px solid #f5f6f8;
        }

        .loan-detail-page .ld-table tr:hover td {
            background: #fafbfc;
        }

        .loan-detail-page .ld-table tr:last-child td {
            border-bottom: none;
        }
    </style>
@endpush

@section('content')
    <div class="loan-detail-page">

        {{-- Breadcrumb --}}
        <div class="ld-breadcrumb">
            <a href="{{ route('loans.index') }}">Loans</a>
            <span class="ld-breadcrumb-sep">›</span>
            <span class="ld-breadcrumb-current">Loan Detail</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: Loan summary --}}
            <div class="space-y-4">

                {{-- Status card --}}
                <div class="ld-card">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <div class="text-xs text-gray-800 mb-1">Loan #{{ $loan->id }}</div>
                            <span
                                class="ld-badge ld-badge-{{ $loan->status }} text-sm py-1 px-3">{{ ucfirst($loan->status) }}</span>
                        </div>
                        @if($loan->status === 'overdue')
                            <div class="text-right">
                                <div class="text-2xl font-display font-800 text-red-400">{{ $loan->days_overdue }}</div>
                                <div class="text-xs text-gray-500">days overdue</div>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Borrower</span>
                            <span class="font-800">{{ $loan->borrower->name }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Group</span>
                            <a href="{{ route('groups.show', $loan->group) }}"
                                class="text-green-600 hover:underline">{{ $loan->group->name }}</a>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Principal</span>
                            <span class="font-600">${{ number_format($loan->principal, 2) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Current Balance</span>
                            <span
                                class="font-800 text-lg {{ $loan->status === 'overdue' ? 'text-red-400' : 'text-gray-600' }}">${{ number_format($loan->balance, 2) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Daily Interest</span>
                            <span class="text-orange-600 font-800">
                                @if($loan->interest_type === 'fixed')
                                    ${{ $loan->interest_value }}/day
                                @else
                                    {{ $loan->interest_value }}%/day (~${{ number_format($loan->calculateDailyInterest(), 2) }})
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Loan Date</span>
                            <span>{{ $loan->loan_date->format('d M Y') }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Due Date</span>
                            <span
                                class="{{ $loan->due_date && $loan->due_date < now() && $loan->status !== 'paid' ? 'text-red-500 font-bold' : '' }}">
                                {{ $loan->due_date ? $loan->due_date->format('d M Y') : 'No end date' }}
                            </span>
                        </div>
                        <div class="flex justify-between border-b border-gray-800 pb-3">
                            <span class="text-gray-600 font-bold">Loan Type</span>
                            <span
                                class="ld-badge {{ $loan->isInstallmentLoan() ? 'ld-badge-active' : 'ld-badge-pending' }}">
                                {{ $loan->isInstallmentLoan() ? 'Installment (' . $loan->duration_months . 'mo)' : 'Lump Sum' }}
                            </span>
                        </div>
                        @if($loan->isInstallmentLoan())
                            <div class="flex justify-between border-b border-gray-800 pb-3">
                                <span class="text-gray-600 font-bold">Monthly Payment</span>
                                <span class="font-600">${{ number_format($loan->monthly_installment, 2) }}</span>
                            </div>
                        @endif
                        @if($loan->hasPenalties())
                            <div class="flex justify-between border-b border-gray-800 pb-3">
                                <span class="text-gray-600 font-bold">Late Penalty</span>
                                <span class="text-red-400">
                                    @if($loan->penalty_type === 'fixed')
                                        ${{ $loan->penalty_value }}/day
                                    @else
                                        {{ $loan->penalty_value }}%/day
                                    @endif
                                    <span class="text-gray-500 text-xs">({{ $loan->grace_days }}d grace)</span>
                                </span>
                            </div>
                        @endif
                        @if($loan->paid_at)
                            <div class="flex justify-between">
                                <span class="text-gray-600 font-bold">Paid At</span>
                                <span class="text-green-400">{{ $loan->paid_at->format('d M Y') }}</span>
                            </div>
                        @endif
                        @if($loan->notes)
                            <div class="pt-2">
                                <span class="text-gray-400 block mb-1">Notes</span>
                                <p class="text-sm text-gray-300">{{ $loan->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Interest log --}}
                <div class="ld-card">
                    <h3 class="ld-card-title mb-4">Interest Log (last 7 days)</h3>
                    @forelse($loan->interestLogs->take(7) as $log)
                        <div class="flex items-center justify-between py-2 border-b border-gray-800 last:border-0 text-sm">
                            <span class="text-gray-700">{{ $log->calculated_date->format('d M') }}</span>
                            <span class="text-orange-600">+${{ number_format($log->interest_applied, 2) }}</span>
                            <span class="text-gray-700">${{ number_format($log->balance_after, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No interest applied yet.</p>
                    @endforelse
                </div>

                @if($loan->isInstallmentLoan() && $loan->installments->count())
                    {{-- Installment Schedule --}}
                    <div class="ld-card">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="ld-card-title">📅 Installment Schedule</h3>
                            <div class="text-xs text-gray-500">
                                {{ $loan->installments->where('status', 'paid')->count() }}/{{ $loan->installments->count() }}
                                paid
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="ld-table text-xs">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Due Date</th>
                                        <th>Base</th>
                                        <th>Penalty</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loan->installments as $inst)
                                        <tr class="{{ $inst->status === 'overdue' ? 'bg-red-500/5' : '' }}">
                                            <td class="font-mono text-gray-400">{{ $inst->installment_number }}</td>
                                            <td class="{{ $inst->is_overdue ? 'text-red-400' : '' }}">
                                                {{ $inst->due_date->format('d M Y') }}
                                            </td>
                                            <td>${{ number_format($inst->base_amount, 2) }}</td>
                                            <td class="{{ $inst->penalty_amount > 0 ? 'text-red-400' : 'text-gray-600' }}">
                                                ${{ number_format($inst->penalty_amount, 2) }}
                                            </td>
                                            <td class="text-green-400">${{ number_format($inst->paid_amount, 2) }}</td>
                                            <td class="font-600 {{ $inst->balance > 0 ? 'text-white' : 'text-green-400' }}">
                                                ${{ number_format($inst->balance, 2) }}
                                            </td>
                                            <td>
                                                <span
                                                    class="ld-badge ld-badge-{{ $inst->status === 'paid' ? 'paid' : ($inst->status === 'overdue' ? 'overdue' : ($inst->status === 'partial' ? 'active' : 'pending')) }}">
                                                    {{ ucfirst($inst->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($inst->penalty_amount > 0 && $inst->status !== 'paid')
                                                    <form method="POST" action="{{ route('loans.waive-penalty', [$loan, $inst->id]) }}"
                                                        onsubmit="return confirm('Waive penalty for installment #{{ $inst->installment_number }}?')">
                                                        @csrf @method('PATCH')
                                                        <button type="submit"
                                                            class="text-xs text-yellow-500 hover:text-yellow-300">Waive</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-gray-700">
                                        <td colspan="2" class="font-600">Total</td>
                                        <td>${{ number_format($loan->installments->sum('base_amount'), 2) }}</td>
                                        <td class="text-red-400">
                                            ${{ number_format($loan->installments->sum('penalty_amount'), 2) }}
                                        </td>
                                        <td class="text-green-400">
                                            ${{ number_format($loan->installments->sum('paid_amount'), 2) }}
                                        </td>
                                        <td class="font-700">${{ number_format($loan->installments->sum('balance'), 2) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: Payments --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Payments list --}}
                <div class="ld-card">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="ld-card-title">Payment History</h3>
                        <div class="text-xs text-gray-500">
                            Total paid: <span
                                class="text-green-600 font-semibold">${{ number_format($loan->payments->where('status', 'approved')->sum('amount'), 2) }}</span>
                        </div>
                    </div>

                    @forelse($loan->payments->sortByDesc('created_at') as $payment)
                        <div class="border border-gray-800 rounded-xl p-4 mb-3 last:mb-0">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span
                                            class="text-lg font-700 {{ $payment->status === 'approved' ? 'text-green-400' : ($payment->status === 'rejected' ? 'text-red-400' : 'text-yellow-400') }}">
                                            ${{ number_format($payment->amount, 2) }}
                                        </span>
                                        <span
                                            class="ld-badge ld-badge-{{ $payment->status === 'approved' ? 'paid' : ($payment->status === 'rejected' ? 'overdue' : 'pending') }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                        <span class="text-xs text-gray-500">{{ ucfirst($payment->type) }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $payment->created_at->format('d M Y H:i') }}
                                        @if($payment->method) · {{ ucfirst(str_replace('_', ' ', $payment->method)) }} @endif
                                    </div>
                                </div>

                                {{-- Approve/Reject actions --}}
                                @if($payment->status === 'pending')
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('payments.approve', $payment) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="ld-btn ld-btn-primary ld-btn-sm">✓ Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('payments.reject', $payment) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="ld-btn ld-btn-danger ld-btn-sm">✗ Reject</button>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            {{-- Payment proof --}}
                            @if($payment->proofs->count())
                                <div class="mt-3 pt-3 border-t border-gray-800">
                                    <div class="text-xs text-gray-500 mb-2">Payment Proof</div>
                                    <div class="flex gap-2 flex-wrap">
                                        @foreach($payment->proofs as $proof)
                                            <a href="{{ Storage::url($proof->file_path) }}" target="_blank"
                                                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-xs hover:border-gray-500 transition-colors">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    class="w-3 h-3">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                                    <polyline points="21 15 16 10 5 21" />
                                                </svg>
                                                {{ $proof->original_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($payment->notes)
                                <p class="text-xs text-gray-500 mt-2">{{ $payment->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-600">
                            <p class="text-sm">No payments recorded yet.</p>
                            <a href="{{ route('payments.create', ['loan_id' => $loan->id]) }}"
                                class="ld-btn ld-btn-primary ld-btn-sm mt-3">Record First Payment</a>
                        </div>
                    @endforelse
                </div>

                {{-- Reminders sent --}}
                <div class="ld-card">
                    <h3 class="ld-card-title mb-4">Reminders Sent</h3>
                    <table class="ld-table">
                        <thead>
                            <tr>
                                <th>Sent At</th>
                                <th>Status</th>
                                <th>Message Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loan->reminders->sortByDesc('sent_at')->take(10) as $reminder)
                                <tr>
                                    <td class="text-xs text-gray-400">
                                        {{ $reminder->sent_at?->format('d M Y H:i') ?? $reminder->scheduled_at->format('d M Y H:i') }}
                                    </td>
                                    <td><span
                                            class="ld-badge ld-badge-{{ $reminder->status === 'sent' ? 'active' : ($reminder->status === 'failed' ? 'overdue' : 'pending') }}">{{ ucfirst($reminder->status) }}</span>
                                    </td>
                                    <td class="text-xs text-gray-400 max-w-xs truncate">
                                        {{ Str::limit(strip_tags($reminder->message_snapshot), 60) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-gray-600 py-4 text-sm">No reminders sent yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
@endsection
