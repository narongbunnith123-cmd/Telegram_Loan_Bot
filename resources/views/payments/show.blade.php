@extends('layouts.app')
@section('title', 'Payment #' . $payment->id)
@section('page-title', 'Payment Detail')
@section('page-subtitle', 'View payment details and proof of payment.')

@push('styles')
    <style>
        .pv-page {}

        .pv-card {
            background: #ffffff;
            border: 1px solid #eef0f3;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
        }

        .pv-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
        }

        .pv-badge-pending {
            background: #fffbeb;
            color: #d97706;
        }

        .pv-badge-approved {
            background: #eafcf1;
            color: #16a34a;
        }

        .pv-badge-rejected {
            background: #FEF3C7;
            color: #dc2626;
        }

        .pv-badge-active {
            background: #eafcf1;
            color: #16a34a;
        }

        .pv-badge-overdue {
            background: #FEF3C7;
            color: #b14d0fff;
        }

        .pv-badge-paid {
            background: #eff6ff;
            color: #2563eb;
        }

        .pv-btn {
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
            text-decoration: none;
            transition: all .15s;
        }

        .pv-btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }

        .pv-btn-primary {
            background: #4379EE;
            color: #ffffff;
        }

        .pv-btn-primary:hover {
            background: #5238e4;
        }

        .pv-btn-ghost {
            background: #ffffff;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .pv-btn-ghost:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .pv-btn-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .pv-btn-danger:hover {
            background: #fee2e2;
        }

        /* everything below is scoped under .pv-page so it can't bleed out */
        .pv-page .pv-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
        }

        .pv-page .pv-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pv-page .pv-section-title svg {
            width: 16px;
            height: 16px;
            stroke: #6b7280;
            flex-shrink: 0;
        }

        .pv-page .pv-proof-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .pv-page .pv-proof-item:last-child {
            margin-bottom: 0;
        }

        .pv-page .pv-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pv-page .pv-empty {
            text-align: center;
            padding: 40px 24px;
            border: 1px dashed #e5e7eb;
            border-radius: 12px;
            color: #6b7280;
        }

        .pv-page .pv-empty-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
        }

        .pv-page .pv-empty-icon svg {
            width: 22px;
            height: 22px;
            stroke: #9ca3af;
        }

        .pv-page .pv-empty-title {
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            margin-bottom: 4px;
        }

        .pv-page .pv-empty-subtitle {
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
@endpush

@push('header-actions')
    <a href="{{ route('loans.show', $payment->loan) }}" class="pv-btn pv-btn-ghost pv-btn-sm">← Back</a>
    @if($payment->status === 'pending')
        <form method="POST" action="{{ route('payments.approve', $payment) }}">
            @csrf @method('PATCH')
            <button type="submit" class="pv-btn pv-btn-primary pv-btn-sm">✓ Approve</button>
        </form>
        <form method="POST" action="{{ route('payments.reject', $payment) }}" onsubmit="return confirm('Reject this payment?')">
            @csrf @method('PATCH')
            <button type="submit" class="pv-btn pv-btn-danger pv-btn-sm">✗ Reject</button>
        </form>
    @endif
@endpush

@section('content')
    <div class="pv-page">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Payment summary --}}
            <div class="pv-card">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <div class="text-xs text-gray-800 mb-2">Payment #{{ $payment->id }}</div>
                        <span
                            class="pv-badge pv-badge-{{ $payment->status }} text-sm py-1 px-3">{{ ucfirst($payment->status) }}</span>
                    </div>
                    <div class="text-right">
                        <div
                            class="text-3xl font-body font-bold {{ $payment->status === 'approved' ? 'text-[#006C49]' : ($payment->status === 'rejected' ? 'text-red-400' : 'text-[#92400E]') }}">
                            ${{ number_format($payment->amount, 2) }}
                        </div>
                    </div>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="pv-row">
                        <span class="text-gray-600">Type</span>
                        <span class="font-500">{{ ucfirst($payment->type ?? 'partial') }}</span>
                    </div>
                    <div class="pv-row">
                        <span class="text-gray-600">Method</span>
                        <span
                            class="font-500">{{ $payment->method ? ucfirst(str_replace('_', ' ', $payment->method)) : '—' }}</span>
                    </div>
                    <div class="pv-row">
                        <span class="text-gray-600 font-medium">Date</span>
                        <span>{{ $payment->created_at->format('d M Y H:i') }}</span>
                    </div>
                    @if($payment->approved_at)
                        <div class="pv-row">
                            <span class="text-gray-600">{{ $payment->status === 'approved' ? 'Approved' : 'Reviewed' }}
                                At</span>
                            <span class="text-[#006C49] font-medium">{{ $payment->approved_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    @if($payment->approvedBy)
                        <div class="pv-row">
                            <span class="text-gray600">Approved By</span>
                            <span>{{ $payment->approvedBy->name }}</span>
                        </div>
                    @endif
                    @if($payment->notes)
                        <div class="pt-1">
                            <span class="text-gray-600 block mb-1">Notes</span>
                            <p class="text-sm text-gray-400">{{ $payment->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Loan info + Proof --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Linked loan --}}
                <div class="pv-card">
                    <h3 class="pv-section-title font-display font-700 text-sm mb-4">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                        </svg>
                        Linked Loan
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-gray-700 mb-1">Borrower</div>
                            <div class="font-semibold">{{ $payment->loan->borrower->name }}</div>
                            @if($payment->loan->borrower->telegram_username)
                                <div class="text-xs text-gray-700">{{'@'}}{{ $payment->loan->borrower->telegram_username }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs text-gray-700 mb-1">Group</div>
                            <div class="font-semibold">{{ $payment->loan->group->name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-700 mb-1">Loan Balance</div>
                            <div
                                class="font-medium {{ $payment->loan->status === 'overdue' ? 'text-[#BA1A1A]' : ($payment->loan->status === 'paid' ? 'text-green-400' : '') }}">
                                ${{ number_format($payment->loan->balance, 2) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-700 mb-1">Loan Status</div>
                            <span
                                class="pv-badge pv-badge-{{ $payment->loan->status }}">{{ ucfirst($payment->loan->status) }}</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-800">
                        <a href="{{ route('loans.show', $payment->loan) }}"
                            class="pv-btn pv-btn-ghost text-[#006C49] pv-btn-sm">View
                            Full Loan Details </a>
                    </div>
                </div>

                {{-- Payment proof --}}
                <div class="pv-card">
                    <h3 class="pv-section-title font-body font-medium text-sm mb-4">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                        </svg>
                        Payment Proof
                    </h3>
                    @forelse($payment->proofs as $proof)
                        <div class="pv-proof-item">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="pv-icon-box">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#8b949e" stroke-width="2" class="w-5 h-5">
                                            <rect x="3" y="3" width="18" height="18" rx="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <polyline points="21 15 16 10 5 21" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-500 text-sm">{{ $proof->original_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            Uploaded {{ $proof->created_at->diffForHumans() }}
                                            @if($proof->uploadedBy) · by {{ $proof->uploadedBy->name }} @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="pv-badge pv-badge-{{ $proof->status }}">{{ ucfirst($proof->status) }}</span>
                                    <a href="{{ Storage::url($proof->file_path) }}" target="_blank"
                                        class="pv-btn pv-btn-ghost pv-btn-sm">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            class="w-3.5 h-3.5">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                            <polyline points="15 3 21 3 21 9" />
                                            <line x1="10" y1="14" x2="21" y2="3" />
                                        </svg>
                                        Open
                                    </a>
                                </div>
                            </div>

                            {{-- Image preview for image files --}}
                            @if(Str::startsWith(pathinfo($proof->original_name, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                <div class="mt-3">
                                    <img src="{{ Storage::url($proof->file_path) }}" alt="{{ $proof->original_name }}"
                                        class="rounded-lg border border-gray-800 max-h-64 object-contain">
                                </div>
                            @endif

                            @if($proof->rejection_reason)
                                <div class="mt-3 p-3 rounded-lg bg-red-900/20 border border-red-900/30 text-sm text-red-400">
                                    <strong>Rejected:</strong> {{ $proof->rejection_reason }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="pv-empty">
                            <div class="pv-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <polyline points="21 15 16 10 5 21" />
                                </svg>
                            </div>
                            <div class="pv-empty-title">No proof uploaded</div>
                            <div class="pv-empty-subtitle font-medium">
                                @if(($payment->method ?? null) === 'simulated')
                                    There is no image or document associated with this simulated payment transaction.
                                @else
                                    There is no image or document associated with this payment.
                                @endif
                            </div>
                        </div>
                    @endforelse
                </div>

            </div>
        </div>
    </div>
@endsection
