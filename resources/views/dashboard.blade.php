@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')


@push('header-actions')
    <a href="{{ route('loans.create') }}" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        New Loan
    </a>
@endpush

@section('content')

    @php
        // Groups: % of groups that are active
        $groupsPct = $stats['total_groups'] > 0
            ? round(($stats['active_groups'] / $stats['total_groups']) * 100)
            : 0;

        // Loans: how many are closed relative to total
        $loansClosed = $stats['total_loans'] - $stats['active_loans'];

        // Unpaid balance: proportion collected vs total (collected + unpaid)
        $balanceTotal = $stats['collected_payments'] + $stats['unpaid_balance'];
        $collectedPct = $balanceTotal > 0
            ? round(($stats['collected_payments'] / $balanceTotal) * 100)
            : 0;

        // A small, consistent palette to color borrower avatars by name
        $avatarPalette = ['#6b7280', '#8b5cf6', '#3b82f6', '#0d9488', '#ea580c', '#db2777'];
    @endphp

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-6">

        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-500">Total Groups</span>
                <div class="icon-box" style="background:#D9D9D9;">
                    <!-- <svg viewBox="0 0 24 24" fill="none" stroke="#ffffffff" stroke-width="2">                                                                   </svg> -->
                    @include('icons.Group.Group', ['width' => 26, 'height' => 26, 'class' => 'text-gray-500'])
                </div>
            </div>
            <div class="text-4xl font-display font-800 text-gray-900 mb-2">{{ $stats['total_groups'] }}</div>
            <span class="trend-text {{ $groupsPct >= 50 ? 'trend-up' : 'trend-flat' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                    <polyline points="17 6 23 6 23 12" />
                </svg>
                +{{ $groupsPct }}%
            </span>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-500">Active Loans</span>
                <div class="icon-box" style="background:#D9D9D9;">
                    <!-- <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                                                                                                                                                                    <rect x="4" y="4" width="16" height="16" rx="3" />
                                                                                                                                                                    <line x1="8" y1="9" x2="16" y2="9" />
                                                                                                                                                                    <line x1="8" y1="13" x2="16" y2="13" />
                                                                                                                                                                    <line x1="8" y1="17" x2="12" y2="17" />
                                                                                                                                                                </svg> -->
                    @include('icons.Loan.Loan', ['width' => 32, 'height' => 32, 'class' => 'text-gray-500'])
                </div>
            </div>
            <div class="text-4xl font-display font-800 text-gray-900 mb-2">{{ $stats['active_loans'] }}</div>
            @if($loansClosed <= 0)
                <span class="trend-text trend-flat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="4" y1="12" x2="20" y2="12" />
                    </svg>
                    Stable
                </span>
            @else
                <span class="trend-text trend-flat">{{ $loansClosed }} Closed</span>
            @endif
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-500">Overdue</span>
                <div class="icon-box" style="background:#D9D9D9;">
                    <!-- <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                                                                                                                                                                                                <path
                                                                                                                                                                                                    d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                                                                                                                                                                                <line x1="12" y1="9" x2="12" y2="13" />
                                                                                                                                                                                                <line x1="12" y1="17" x2="12.01" y2="17" />
                                                                                                                                                                                            </svg> -->
                    @include('icons.Overdue.Overdue', ['width' => 28, 'height' => 28, 'class' => 'text-gray-700'])
                </div>
            </div>
            <div class="text-4xl font-display font-800 text-gray-900 mb-2">{{ $stats['overdue_loans'] }}</div>
            @if($stats['overdue_loans'] > 0)
                <span class="trend-text trend-bad">
                    <span class="dot" style="background:#ef4444;"></span>
                    Red Critical
                </span>
            @else
                <span class="trend-text trend-up">
                    <span class="dot" style="background:#16a34a;"></span>
                    All Clear
                </span>
            @endif
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-500">Unpaid Balance</span>
                <div class="icon-box" style="background:#D9D9D9;">
                    <!-- <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2">
                                                                                                                                                                        <path
                                                                                                                                                                            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                                                                                                                                                        <line x1="12" y1="9" x2="12" y2="13" />
                                                                                                                                                                        <line x1="12" y1="17" x2="12.01" y2="17" />
                                                                                                                                                                    </svg> -->
                    @include('icons.Unpaid.Unpaid', ['width' => 32, 'height' => 32, 'class' => 'text-gray-700'])
                </div>
            </div>
            <div class="text-4xl font-body font-800 text-gray-900 mb-2">${{ number_format($stats['unpaid_balance'], 2) }}
            </div>
            <div class="text-sm text-gray-400">Collected: <span
                    class="text-sm text-green-600 font-bold">${{ number_format($stats['collected_payments'], 2) }}</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:{{ $collectedPct }}%;"></div>
            </div>
        </div>
    </div>

    {{-- Two column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Recent overdue loans --}}
        <div class="lg:col-span-2 card">
            <h2 class="font-body font-bold text-xl text-gray-700 mb-5">Overdue Loans</h2>

            @if($overdueLoans->isEmpty())
                <div class="empty-state">
                    <div class="icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-6 h-6">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500">No overdue loans </p>
                </div>
            @else
                <div class="table-container1">
                    <table class="data-table1">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Balance</th>
                                <th>Overdue Days</th>
                                <th>Interest/Day</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($overdueLoans as $loan)
                                @php
                                    $nameParts = preg_split('/\s+/', trim($loan->borrower->name));
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1] ?? $nameParts[0], isset($nameParts[1]) ? 0 : 1, 1));
                                    $avatarColor = $avatarPalette[crc32($loan->borrower->name) % count($avatarPalette)];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar" style="background:{{ $avatarColor }};">{{ $initials }}</div>
                                            <span class="font-500 text-gray-900">{{ $loan->borrower->name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-gray-900">${{ number_format($loan->balance, 2) }}</td>
                                    <td class="text-gray-900">{{ $loan->days_overdue }} d</td>
                                    <td class="text-gray-900">
                                        @if($loan->interest_type === 'fixed')
                                            ${{ $loan->interest_value }}/day
                                        @else
                                            {{ $loan->interest_value }}%/day
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('loans.show', $loan) }}" class="link-action">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination-bar">
                    <span class="mr-1">Page</span>
                    <button class="page-pill active">1</button>
                    <a href="{{ route('loans.index', ['status' => 'overdue']) }}" class="page-pill">2</a>
                    <a href="{{ route('loans.index', ['status' => 'overdue']) }}" class="page-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </a>
                </div>
            @endif
        </div>

        {{-- Today's reminders + pending payments --}}
        <div class="space-y-5">

            {{-- Pending payment proofs --}}
            <div class="card">
                <h2 class="font-body font-bold text-xl text-orange-400 mb-2">Pending Proofs</h2>
                @forelse($pendingProofs->take(5) as $proof)
                    <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                        <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#005effff" stroke-width="2" class="w-4 h-4">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-500 truncate text-gray-900">{{ $proof->payment->loan->borrower->name }}
                            </div>
                            <div class="text-xs text-gray-500">${{ $proof->payment->amount }}</div>
                        </div>
                        <a href="{{ route('payments.show', $proof->payment) }}" class="link-action">Review</a>
                    </div>
                @empty
                    <div class="empty-state !py-6">
                        <div class="icon-wrap">
                            <!-- <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                                                                                                                                                                                                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                                                                                                                                                                                                <polyline points="14 2 14 8 20 8" />
                                                                                                                                                                                                            </svg> -->
                            @include('icons.Proof.Proof', ['width' => 35, 'height' => 35, 'class' => 'text-gray-500'])
                        </div>
                        <p class="text-sm text-gray-500">No pending proofs</p>
                    </div>
                @endforelse
            </div>

            {{-- Today's reminders sent --}}
            <div class="card">
                <h2 class="font-serif font-800 text-lg text-gray-900 mb-3">Reminders Today</h2>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-4xl font-display font-800 text-gray-900">{{ $todayReminders }}</span>
                    @if($failedReminders > 0)
                        <span class="badge badge-overdue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3 h-3">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                            Attention
                        </span>
                    @else
                        <span class="badge badge-success">
                            <!-- <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3 h-3">
                                                                                                                                                                        <polyline points="20 6 9 17 4 12" />
                                                                                                                                                                    </svg> -->
                            Success
                        </span>
                    @endif
                </div>
                <div class="flex flex-row">
                    <span>{{ $failedReminders }}</span>
                    <p class="text-sm text-red-400 ml-2"> failed reminders</p>
                </div>

            </div>

        </div>
    </div>

@endsection
