{{-- resources/views/payments/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Payments')
@section('page-title', 'Payments')
@section('page-subtitle', 'View and manage all payment transactions.')

@push('header-actions')
    <a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Record Payment
    </a>
@endpush

@section('content')
    <div class="card">
        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <input type="text" name="search" value="{{ request('search') }}" class="form-input" style="width:200px;"
                placeholder="Search borrower…">
            <select name="group_id" class="form-input form-select" style="width:180px;">
                <option value="">All Groups</option>
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" {{ request('group_id') == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-input form-select" style="width:140px;">
                <option value="">All Status</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
            <a href="{{ route('payments.index') }}" class="btn btn-ghost btn-sm">Clear</a>
        </form>

        <div class="table-container">
            <table class="data-table1">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Group</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td class="font-500">{{ $payment->loan->borrower->name }}</td>
                            <td class="text-sm text-gray-400">{{ $payment->loan->group->name }}</td>
                            <td
                                class="font-600 {{ $payment->status === 'approved' ? 'text-green-400' : ($payment->status === 'rejected' ? 'text-red-400' : 'text-yellow-400') }}">
                                ${{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="text-xs text-gray-400">{{ ucfirst($payment->type ?? '—') }}</td>
                            <td class="text-xs text-gray-400">
                                {{ $payment->method ? ucfirst(str_replace('_', ' ', $payment->method)) : '—' }}
                            </td>
                            <td>
                                <span class="badge badge-{{ $payment->status }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="text-xs text-gray-400">{{ $payment->created_at->format('d M Y H:i') }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('payments.show', $payment) }}" class="btn btn-ghost btn-sm">View</a>
                                    <a href="{{ route('loans.show', $payment->loan) }}" class="btn btn-ghost btn-sm">Loan</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-gray-500 py-10">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $payments->appends(request()->query())->links() }}</div>
    </div>
@endsection
