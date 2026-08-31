{{-- resources/views/payments/sessions.blade.php --}}
@extends('layouts.app')
@section('title', 'Payment Sessions')
@section('page-title', 'Payment Sessions')
@section('page-subtitle', 'View all payment collection sessions.')

@push('header-actions')
    <a href="{{ route('payments.index') }}" class="btn btn-ghost btn-sm">
        ← Back to Payments
    </a>
@endpush

@section('content')
<div class="card">
    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        <input type="text" name="search" value="{{ request('search') }}"
               class="form-input" style="width:200px;" placeholder="Search reference…">
        <select name="status" class="form-input form-select" style="width:160px;">
            <option value="">All Status</option>
            <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
            <option value="paid"      {{ request('status') === 'paid'      ? 'selected' : '' }}>Paid</option>
            <option value="expired"   {{ request('status') === 'expired'   ? 'selected' : '' }}>Expired</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            <option value="failed"    {{ request('status') === 'failed'    ? 'selected' : '' }}>Failed</option>
        </select>
        <select name="gateway" class="form-input form-select" style="width:140px;">
            <option value="">All Gateways</option>
            <option value="mock" {{ request('gateway') === 'mock' ? 'selected' : '' }}>Mock</option>
            <option value="khqr" {{ request('gateway') === 'khqr' ? 'selected' : '' }}>KHQR</option>
        </select>
        <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        <a href="{{ route('payments.sessions') }}" class="btn btn-ghost btn-sm">Clear</a>
    </form>

    {{-- Stats --}}
    <div class="flex flex-wrap gap-4 mb-5">
        <div class="px-4 py-2 rounded-lg" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2);">
            <span class="text-xs text-gray-400">Active</span>
            <div class="text-lg font-600 text-blue-400">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="px-4 py-2 rounded-lg" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2);">
            <span class="text-xs text-gray-400">Paid Today</span>
            <div class="text-lg font-600 text-green-400">{{ $stats['paid_today'] ?? 0 }}</div>
        </div>
        <div class="px-4 py-2 rounded-lg" style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.2);">
            <span class="text-xs text-gray-400">Expired Today</span>
            <div class="text-lg font-600 text-yellow-400">{{ $stats['expired_today'] ?? 0 }}</div>
        </div>
        <div class="px-4 py-2 rounded-lg" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);">
            <span class="text-xs text-gray-400">Failed</span>
            <div class="text-lg font-600 text-red-400">{{ $stats['failed'] ?? 0 }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Borrower</th>
                <th>Amount</th>
                <th>Gateway</th>
                <th>Status</th>
                <th>Created</th>
                <th>Expires</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $session)
            <tr>
                <td>
                    <code class="text-xs" style="background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;">
                        {{ $session->reference_code }}
                    </code>
                </td>
                <td class="font-500">{{ $session->borrower->name ?? '—' }}</td>
                <td class="font-600">
                    ${{ number_format($session->amount, 2) }}
                    @if($session->currency !== 'USD')
                        <span class="text-xs text-gray-400">({{ $session->currency }})</span>
                    @endif
                </td>
                <td class="text-xs">
                    @switch($session->gateway_name)
                        @case('mock')
                            <span class="text-gray-400">🧪 Mock</span>
                            @break
                        @case('khqr')
                            <span class="text-blue-400">🏦 KHQR</span>
                            @break
                        @default
                            <span class="text-gray-400">{{ ucfirst($session->gateway_name) }}</span>
                    @endswitch
                </td>
                <td>
                    @php
                        $statusColor = match($session->status) {
                            'pending' => 'badge-pending',
                            'paid' => 'badge-approved',
                            'expired' => 'badge-rejected',
                            'cancelled' => 'badge-rejected',
                            'failed' => 'badge-rejected',
                            default => '',
                        };
                    @endphp
                    <span class="badge {{ $statusColor }}">
                        {{ ucfirst($session->status) }}
                    </span>
                    @if($session->status === 'pending' && $session->isExpired())
                        <span class="text-xs text-red-400 ml-1">(expired)</span>
                    @elseif($session->status === 'pending')
                        <span class="text-xs text-gray-400 ml-1">{{ $session->remaining_time }}</span>
                    @endif
                </td>
                <td class="text-xs text-gray-400">{{ $session->created_at->format('d M H:i') }}</td>
                <td class="text-xs text-gray-400">
                    @if($session->expires_at)
                        {{ $session->expires_at->format('d M H:i') }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    <a href="{{ route('payments.session-detail', $session) }}" class="btn btn-ghost btn-sm">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-gray-500 py-10">No payment sessions found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $sessions->appends(request()->query())->links() }}</div>
</div>
@endsection
