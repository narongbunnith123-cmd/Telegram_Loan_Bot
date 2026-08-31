{{-- resources/views/payments/session-detail.blade.php --}}
@extends('layouts.app')
@section('title', 'Session: ' . $session->reference_code)
@section('page-title', 'Payment Session Detail')
@section('page-subtitle', 'Review payments collected in this session.')

@push('header-actions')
    <a href="{{ route('payments.sessions') }}" class="btn btn-ghost btn-sm">
        ← Back to Sessions
    </a>
@endpush

@section('content')
    <div class="grid gap-6" style="grid-template-columns: 1fr 1fr;">

        {{-- Session Info --}}
        <div class="card">
            <h3 class="text-lg font-600 mb-4">Session Info</h3>

            @php
                $statusColor = match ($session->status) {
                    'pending' => 'badge-pending',
                    'paid' => 'badge-approved',
                    'expired', 'cancelled', 'failed' => 'badge-rejected',
                    default => '',
                };
            @endphp

            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-400">Reference</span>
                    <code class="text-sm" style="background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 4px;">
                        {{ $session->reference_code }}
                    </code>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Status</span>
                    <span class="badge {{ $statusColor }}">{{ ucfirst($session->status) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Amount</span>
                    <span class="font-600">${{ number_format($session->amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Currency</span>
                    <span>{{ $session->currency }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Gateway</span>
                    <span>{{ ucfirst($session->gateway_name) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Created</span>
                    <span class="text-sm">{{ $session->created_at->format('d M Y H:i:s') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Expires</span>
                    <span class="text-sm">
                        @if($session->expires_at)
                            {{ $session->expires_at->format('d M Y H:i:s') }}
                            @if($session->status === 'pending')
                                <span class="text-xs {{ $session->isExpired() ? 'text-red-400' : 'text-green-400' }}">
                                    ({{ $session->remaining_time }})
                                </span>
                            @endif
                        @else
                            —
                        @endif
                    </span>
                </div>
                @if($session->paid_at)
                    <div class="flex justify-between">
                        <span class="text-gray-400">Paid At</span>
                        <span class="text-sm text-green-400">{{ $session->paid_at->format('d M Y H:i:s') }}</span>
                    </div>
                @endif
                @if($session->transaction_id)
                    <div class="flex justify-between">
                        <span class="text-gray-400">Transaction ID</span>
                        <code class="text-xs">{{ $session->transaction_id }}</code>
                    </div>
                @endif
            </div>

            @if($session->checkout_url)
                <div class="mt-4 p-3 rounded-lg" style="background: rgba(59, 130, 246, 0.1);">
                    <span class="text-xs text-gray-400">Checkout URL</span>
                    <div class="text-sm break-all mt-1">
                        <a href="{{ $session->checkout_url }}" target="_blank" class="text-blue-400 hover:underline">
                            {{ $session->checkout_url }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Borrower & Loan Info --}}
        <div class="card">
            <h3 class="text-lg font-600 mb-4">Loan Info</h3>

            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-400">Borrower</span>
                    <a href="{{ route('borrowers.show', $session->borrower) }}" class="text-blue-400 hover:underline">
                        {{ $session->borrower->name }}
                    </a>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Loan</span>
                    <a href="{{ route('loans.show', $session->loan) }}" class="text-blue-400 hover:underline">
                        Loan #{{ $session->loan->id }}
                    </a>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Loan Status</span>
                    <span class="badge badge-{{ $session->loan->status }}">{{ ucfirst($session->loan->status) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Principal</span>
                    <span>${{ number_format($session->loan->remaining_principal, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Balance</span>
                    <span>${{ number_format($session->loan->balance, 2) }}</span>
                </div>
            </div>

            @if($session->payment)
                <div class="mt-6">
                    <h4 class="text-md font-500 mb-3">Linked Payment</h4>
                    <div class="p-3 rounded-lg"
                        style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div class="flex justify-between mb-1">
                            <span class="text-gray-400">Payment ID</span>
                            <a href="{{ route('payments.show', $session->payment) }}" class="text-green-400 hover:underline">
                                #{{ $session->payment->id }}
                            </a>
                        </div>
                        <div class="flex justify-between mb-1">
                            <span class="text-gray-400">Amount</span>
                            <span class="text-green-400 font-600">${{ number_format($session->payment->amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Status</span>
                            <span
                                class="badge badge-{{ $session->payment->status }}">{{ ucfirst($session->payment->status) }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Webhook Payload --}}
    @if($session->webhook_payload)
        <div class="card mt-6">
            <h3 class="text-lg font-600 mb-4">Webhook Payload</h3>
            <pre class="p-4 rounded-lg text-sm overflow-x-auto"
                style="background: rgba(0,0,0,0.3); max-height: 400px;">{{ json_encode($session->webhook_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif

    {{-- Metadata --}}
    @if($session->metadata)
        <div class="card mt-6">
            <h3 class="text-lg font-600 mb-4">Metadata</h3>
            <pre class="p-4 rounded-lg text-sm overflow-x-auto"
                style="background: rgba(0,0,0,0.3); max-height: 300px;">{{ json_encode($session->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
@endsection