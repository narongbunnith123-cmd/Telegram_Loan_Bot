{{-- resources/views/loans/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Loans')
@section('page-title', 'Loans')
@section('page-subtitle', 'Track and manage all loan records.')

@push('header-actions')
    <a href="{{ route('loans.create') }}" class="btn btn-primary btn-sm">+ New Loan</a>
@endpush

@section('content')
    <div class="card">
        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <input type="text" name="search" value="{{ request('search') }}" class="form-input" style="width:200px;"
                placeholder="Search borrower…">
            <select name="status" class="form-input form-select" style="width:140px;">
                <option value="">All Status</option>
                @foreach(['active', 'overdue', 'paid', 'pending', 'cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="group_id" class="form-input form-select" style="width:180px;">
                <option value="">All Groups</option>
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" {{ request('group_id') == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
            <a href="{{ route('loans.index') }}" class="btn btn-ghost btn-sm">Clear</a>
        </form>
        <div class="table-container">
            <table class="data-table1">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Group</th>
                        <th>Principal</th>
                        <th>Balance</th>
                        <th>Interest</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td>
                                <div class="font-600">{{ $loan->borrower->name }}</div>
                                @if($loan->borrower->telegram_username)
                                    <div class="text-xs text-gray-500">{{ '@' . $loan->borrower->telegram_username }}</div>
                                @endif
                            </td>
                            <td class="text-sm text-gray-400">{{ $loan->group->name }}</td>
                            <td>${{ number_format($loan->principal, 2) }}</td>
                            <td class="{{ $loan->status === 'overdue' ? 'text-red-400 font-600' : '' }}">
                                ${{ number_format($loan->balance, 2) }}
                            </td>
                            <td class="text-xs text-gray-400">
                                @if($loan->interest_type === 'fixed')
                                    ${{ $loan->interest_value }}/day
                                @else
                                    {{ $loan->interest_value }}%/day
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                            <td
                                class="text-xs {{ $loan->due_date && $loan->due_date < now() && $loan->status !== 'paid' ? 'text-red-400' : 'text-gray-400' }}">
                                {{ $loan->due_date ? $loan->due_date->format('d M Y') : 'No end date' }}
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-ghost btn-sm">View</a>
                                    <a href="{{ route('loans.edit', $loan) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    @if(in_array($loan->status, ['active', 'overdue']))
                                        <a href="{{ route('payments.create', ['loan_id' => $loan->id]) }}"
                                            class="btn btn-primary btn-sm">Pay</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-gray-500 py-10">No loans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $loans->appends(request()->query())->links() }}</div>
    </div>
@endsection
