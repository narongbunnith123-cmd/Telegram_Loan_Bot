@extends('layouts.app')
@section('title', $borrower->name . ' — Borrower')
@section('page-title', $borrower->name)
@section('page-subtitle', 'View borrower profile, loans, and payment history.')

@push('header-actions')
    <a href="{{ route('borrowers.index') }}" class="btn btn-ghost btn-sm">← Back</a>
@endpush

@push('styles')
    <style>

        /* Back link at top */
        #borrower-detail .bd-back-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            font-weight: 600;
            color: #16a34a;
            text-decoration: none;
            margin-bottom: 4px;
        }

        #borrower-detail .bd-back-link:hover {
            text-decoration: underline;
        }

        #borrower-detail .bd-page-name {
            font-family: Arial, sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 4px 0;
            line-height: 1.3;
        }

        #borrower-detail .bd-page-sub {
            font-size: 14.5px;
            color: #6b7280;
            margin: 0 0 28px 0;
        }

        /* Card container */
        #borrower-detail .bd-card {
            background: #ffffff;
            border: 1px solid #e8eaef;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 1px 8px rgba(16, 24, 40, .03);
            margin-bottom: 18px;
        }

        /* Section title with icon */
        #borrower-detail .bd-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: Arial, sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: #111827;
            margin-bottom: 18px;
        }

        #borrower-detail .bd-section-title .bd-icon {
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #borrower-detail .bd-section-title .bd-icon svg {
            width: 20px;
            height: 20px;
        }

        /* Badges row in borrower info header */
        #borrower-detail .bd-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        #borrower-detail .bd-badges {
            display: flex;
            gap: 10px;
        }

        #borrower-detail .bd-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid transparent;
        }

        #borrower-detail .bd-badge-active {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        #borrower-detail .bd-badge-blacklisted {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        #borrower-detail .bd-badge-linked {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        #borrower-detail .bd-badge-unlinked {
            background: #f3f4f6;
            color: #6b7280;
            border-color: #e5e7eb;
        }

        /* Info grid — 3 columns */
        #borrower-detail .bd-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px 32px;
        }

        @media (max-width: 768px) {
            #borrower-detail .bd-info-grid {
                grid-template-columns: 1fr;
            }
        }

        #borrower-detail .bd-info-grid .bd-field-label {
            font-size: 11px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        #borrower-detail .bd-info-grid .bd-field-value {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            line-height: 1.5;
        }

        #borrower-detail .bd-info-grid .bd-field-value.bd-code {
            font-family: 'DM Sans', monospace;
            font-weight: 700;
            color: #16a34a;
            font-size: 16px;
            letter-spacing: 0.3px;
        }

        #borrower-detail .bd-notes-row {
            grid-column: 1 / -1;
        }

        /* ─── Telegram Integration — individual cards in row ─── */
        #borrower-detail .bd-tg-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            #borrower-detail .bd-tg-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        #borrower-detail .bd-tg-cell {
            background: #f8faf9;
            border: 1px solid #e8eaef;
            border-radius: 10px;
            padding: 12px 16px;
        }

        #borrower-detail .bd-tg-cell .bd-tg-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        #borrower-detail .bd-tg-cell .bd-tg-value {
            font-size: 14.5px;
            font-weight: 600;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        #borrower-detail .bd-tg-cell .bd-tg-value .bd-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        #borrower-detail .bd-dot-green {
            background: #16a34a;
        }

        #borrower-detail .bd-dot-gray {
            background: #9ca3af;
        }

        /* Invite link box */
        #borrower-detail .bd-invite-box {
            background: #1e293b;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
        }

        #borrower-detail .bd-invite-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #4ade80;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        #borrower-detail .bd-invite-row {
            display: flex;
            gap: 12px;
            align-items: stretch;
        }

        #borrower-detail .bd-invite-input {
            flex: 1;
            background: #334155;
            border: 1px solid #475569;
            border-radius: 10px;
            padding: 12px 18px;
            color: #e2e8f0;
            font-family: 'DM Sans', monospace;
            font-size: 14px;
            outline: none;
            transition: border-color .15s;
        }

        #borrower-detail .bd-invite-input:focus {
            border-color: #4ade80;
        }

        #borrower-detail .bd-copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #ffffff;
            color: #111827;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
            font-family: 'DM Sans', sans-serif;
        }

        #borrower-detail .bd-copy-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        #borrower-detail .bd-invite-hint {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 12px;
            font-style: italic;
        }

        /* Action buttons row */
        #borrower-detail .bd-actions-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        #borrower-detail .bd-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: all .15s;
            background: none;
            font-family: 'DM Sans', sans-serif;
        }

        #borrower-detail .bd-action-btn-primary {
            color: #16a34a;
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        #borrower-detail .bd-action-btn-primary:hover {
            background: #dcfce7;
            border-color: #86efac;
        }

        #borrower-detail .bd-action-btn-danger {
            color: #dc2626;
            border-color: #fecaca;
            background: #fef2f2;
        }

        #borrower-detail .bd-action-btn-danger:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        /* ─── Loan Summary section (standalone, not in card) ─── */
        #borrower-detail .bd-summary-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: Arial, sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #111827;
            margin-bottom: 18px;
        }

        #borrower-detail .bd-summary-title .bd-icon {
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #borrower-detail .bd-summary-title .bd-icon svg {
            width: 22px;
            height: 22px;
        }

        #borrower-detail .bd-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            #borrower-detail .bd-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        #borrower-detail .bd-stat-card {
            background: #ffffff;
            border: 1px solid #e8eaef;
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: box-shadow .2s, border-color .2s;
        }

        #borrower-detail .bd-stat-card:hover {
            box-shadow: 0 4px 16px rgba(16, 24, 40, .06);
            border-color: #d1d5db;
        }

        #borrower-detail .bd-stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #borrower-detail .bd-stat-icon svg {
            width: 18px;
            height: 18px;
        }

        #borrower-detail .bd-stat-icon-blue {
            background: #eff6ff;
            color: #3b82f6;
        }

        #borrower-detail .bd-stat-icon-red {
            background: #fef2f2;
            color: #ef4444;
        }

        #borrower-detail .bd-stat-icon-orange {
            background: #fff7ed;
            color: #f97316;
        }

        #borrower-detail .bd-stat-icon-green {
            background: #f0fdf4;
            color: #22c55e;
        }

        #borrower-detail .bd-stat-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        #borrower-detail .bd-stat-value {
            font-family: 'Oxygen';
            font-style: normal;
            font-size: 21px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
        }

        /* ─── Loan History Section ─── */
        #borrower-detail .bd-history-card {
            background: #ffffff;
            border: 1px solid #e8eaef;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 1px 8px rgba(16, 24, 40, .03);
            border-left: 4px solid #e8eaef;
        }

        #borrower-detail .bd-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        #borrower-detail .bd-table-title {
            font-family: Arial, sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #111827;
        }

        #borrower-detail .bd-filter-link {
            font-size: 14px;
            font-weight: 600;
            color: #3b82f6;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        #borrower-detail .bd-filter-link:hover {
            text-decoration: underline;
        }

        /* Loan table */
        #borrower-detail .bd-loan-table {
            width: 100%;
            border-collapse: collapse;
        }

        #borrower-detail .bd-loan-table th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11.5px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #e8eaef;
        }

        #borrower-detail .bd-loan-table td {
            padding: 12px 14px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
        }

        #borrower-detail .bd-loan-table tr:last-child td {
            border-bottom: none;
        }

        #borrower-detail .bd-loan-table tr:hover td {
            background: #fafbfc;
        }

        #borrower-detail .bd-loan-table .bd-id {
            font-family: 'DM Sans', monospace;
            font-weight: 700;
            color: #111827;
        }

        #borrower-detail .bd-loan-table .bd-amount {
            font-weight: 600;
        }

        #borrower-detail .bd-loan-table .bd-status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        #borrower-detail .bd-status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        #borrower-detail .bd-status-paid {
            background: #dbeafe;
            color: #2563eb;
        }

        #borrower-detail .bd-status-overdue {
            background: #fee2e2;
            color: #dc2626;
        }

        #borrower-detail .bd-status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        #borrower-detail .bd-status-completed {
            background: #d1fae5;
            color: #059669;
        }

        #borrower-detail .bd-status-defaulted {
            background: #ffedd5;
            color: #ea580c;
        }

        #borrower-detail .bd-view-btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 18px;
            background: #f1f5f9;
            color: #334155;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all .15s;
            border: 1px solid #e2e8f0;
        }

        #borrower-detail .bd-view-btn:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        /* Empty state */
        #borrower-detail .bd-empty {
            text-align: center;
            padding: 40px 16px;
            color: #9ca3af;
            font-size: 14.5px;
        }

        #borrower-detail .bd-empty a {
            color: #16a34a;
            font-weight: 600;
            text-decoration: none;
        }

        #borrower-detail .bd-empty a:hover {
            text-decoration: underline;
        }
    </style>
@endpush

@section('content')
    <div id="borrower-detail">

        {{-- Section 1 — Borrower Information --}}
        <div class="bd-card">
            <div class="bd-header-row">
                <div class="bd-section-title" style="margin-bottom: 0;">
                    <div class="bd-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #6b7280;">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    Borrower Information
                </div>
                <div class="bd-badges">
                    <span class="bd-badge {{ $borrower->status === 'blacklisted' ? 'bd-badge-blacklisted' : 'bd-badge-active' }}">
                        <span style="width:8px;height:8px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                        {{ ucfirst($borrower->status) }}
                    </span>
                    @php $vb = $borrower->verification_badge; @endphp
                    <span class="bd-badge {{ $borrower->isLinked() ? 'bd-badge-linked' : 'bd-badge-unlinked' }}">
                        {{ $vb['icon'] }} {{ $vb['label'] }}
                    </span>
                </div>
            </div>

            <div class="bd-info-grid">
                <div>
                    <div class="bd-field-label">Full Name</div>
                    <div class="bd-field-value">{{ $borrower->name }}</div>
                </div>
                <div>
                    <div class="bd-field-label">Phone</div>
                    <div class="bd-field-value">{{ $borrower->phone_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="bd-field-label">Borrower Code</div>
                    <div class="bd-field-value bd-code">{{ $borrower->borrower_code ?? '—' }}</div>
                </div>
                <div>
                    <div class="bd-field-label">Address</div>
                    <div class="bd-field-value">{{ $borrower->address ?? '—' }}</div>
                </div>
                <div>
                    <div class="bd-field-label">Onboarding Source</div>
                    <div class="bd-field-value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $borrower->onboarding_source) }}</div>
                </div>
                <div>
                    <div class="bd-field-label">Created</div>
                    <div class="bd-field-value">{{ $borrower->created_at->format('d M Y, H:i') }}@if($borrower->creator) by {{ $borrower->creator->name }}@endif</div>
                </div>
                @if($borrower->notes)
                    <div class="bd-notes-row">
                        <div class="bd-field-label">Notes</div>
                        <div class="bd-field-value">{{ $borrower->notes }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Section 2 — Telegram Integration --}}
        <div class="bd-card">
            <div class="bd-section-title">
                <div class="bd-icon">
                    <svg viewBox="0 0 24 24" fill="none" style="color: #6b7280;">
                        <rect x="2" y="3" width="8" height="8" rx="2" stroke="currentColor" stroke-width="2"/>
                        <rect x="14" y="3" width="8" height="8" rx="2" stroke="currentColor" stroke-width="2"/>
                        <rect x="2" y="13" width="8" height="8" rx="2" stroke="currentColor" stroke-width="2"/>
                        <rect x="14" y="13" width="8" height="8" rx="2" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                Telegram Integration
            </div>

            <div class="bd-tg-grid">
                <div class="bd-tg-cell">
                    <div class="bd-tg-label">Status</div>
                    <div class="bd-tg-value">
                        @php $vb = $borrower->verification_badge; @endphp
                        <span class="bd-dot {{ $borrower->isLinked() ? 'bd-dot-green' : 'bd-dot-gray' }}"></span>
                        {{ $vb['label'] }}
                    </div>
                </div>
                <div class="bd-tg-cell">
                    <div class="bd-tg-label">Username</div>
                    <div class="bd-tg-value">{{ $borrower->telegram_username ? '@' . $borrower->telegram_username : '—' }}</div>
                </div>
                <div class="bd-tg-cell">
                    <div class="bd-tg-label">Telegram ID</div>
                    <div class="bd-tg-value" style="font-family: 'DM Sans', monospace;">{{ $borrower->telegram_user_id ?? '—' }}</div>
                </div>
                <div class="bd-tg-cell">
                    <div class="bd-tg-label">Linked At</div>
                    <div class="bd-tg-value">{{ $borrower->linked_at?->format('d M Y, H:i') ?? '—' }}</div>
                </div>
            </div>

            {{-- Invite Link --}}
            @if($borrower->borrower_code)
                <div class="bd-invite-box">
                    <div class="bd-invite-label">Invite Link (share with borrower)</div>
                    <div class="bd-invite-row">
                        <input type="text" id="invite-link" value="{{ $borrower->deep_link ?? 'No bot connected' }}"
                            class="bd-invite-input" readonly>
                        <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('invite-link').value); this.innerHTML='✅ Copied!'; setTimeout(() => this.innerHTML='📋 Copy', 2000);"
                            class="bd-copy-btn">📋 Copy</button>
                    </div>
                    <p class="bd-invite-hint">When the borrower clicks this link, their Telegram account is automatically linked to this profile.</p>
                </div>
            @endif

            <div class="bd-actions-row">
                <form method="POST" action="{{ route('borrowers.invite', $borrower) }}">
                    @csrf
                    <button type="submit" class="bd-action-btn bd-action-btn-primary">
                        ↻ {{ $borrower->borrower_code ? 'Regenerate Code' : 'Generate Invite' }}
                    </button>
                </form>

                @if($borrower->isLinked())
                    <form method="POST" action="{{ route('borrowers.unlink', $borrower) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="bd-action-btn bd-action-btn-danger"
                            onclick="return confirm('Unlink this borrower\'s Telegram account?')">🔗 Unlink</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Section 3 — Loan Summary (standalone section, not in card) --}}
        <div class="bd-summary-title">
            <div class="bd-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #6b7280;">
                    <rect x="2" y="3" width="20" height="18" rx="2"/>
                    <path d="M2 9h20"/>
                    <path d="M10 15h4"/>
                </svg>
            </div>
            Loan Summary
        </div>

        {{-- Stats Cards --}}
        <div class="bd-stats-grid">
            <div class="bd-stat-card">
                <div class="bd-stat-icon bd-stat-icon-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="bd-stat-label">Total Loans</div>
                    <div class="bd-stat-value">{{ $stats['total_loans'] }}</div>
                </div>
            </div>
            <div class="bd-stat-card">
                <div class="bd-stat-icon bd-stat-icon-red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="18" rx="2"/>
                        <path d="M2 9h20"/>
                        <path d="M10 15h4"/>
                    </svg>
                </div>
                <div>
                    <div class="bd-stat-label">Outstanding</div>
                    <div class="bd-stat-value">${{ number_format($stats['outstanding'], 2) }}</div>
                </div>
            </div>
            <div class="bd-stat-card">
                <div class="bd-stat-icon bd-stat-icon-orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="bd-stat-label">Overdue</div>
                    <div class="bd-stat-value">{{ $stats['overdue_count'] }}</div>
                </div>
            </div>
            <div class="bd-stat-card">
                <div class="bd-stat-icon bd-stat-icon-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="bd-stat-label">Total Paid</div>
                    <div class="bd-stat-value">${{ number_format($stats['total_paid'], 2) }}</div>
                </div>
            </div>
        </div>

        {{-- Loan History — in its own card --}}
        <div class="bd-history-card">
            <div class="bd-table-header">
                <div class="bd-table-title">Loan History</div>
                <span class="bd-filter-link">Filter
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                </span>
            </div>

            @if($borrower->loans->count() > 0)
                <table class="bd-loan-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Group</th>
                            <th>Principal</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($borrower->loans->sortByDesc('created_at') as $loan)
                            <tr>
                                <td class="bd-id">#{{ $loan->id }}</td>
                                <td>{{ $loan->group?->name ?? '—' }}</td>
                                <td class="bd-amount">${{ number_format($loan->principal, 2) }}</td>
                                <td class="bd-amount" style="color: {{ $loan->balance > 0 ? '#dc2626' : '#16a34a' }}; font-weight: 700;">
                                    ${{ number_format($loan->balance, 2) }}
                                </td>
                                <td>
                                    @php
                                        $statusClass = match($loan->status) {
                                            'active' => 'bd-status-active',
                                            'paid' => 'bd-status-paid',
                                            'overdue' => 'bd-status-overdue',
                                            'completed' => 'bd-status-completed',
                                            'defaulted' => 'bd-status-defaulted',
                                            default => 'bd-status-pending',
                                        };
                                    @endphp
                                    <span class="bd-status-pill {{ $statusClass }}">{{ ucfirst($loan->status) }}</span>
                                </td>
                                <td style="color: #6b7280;">
                                    {{ $loan->due_date ? $loan->due_date->format('d M Y') : 'No end date' }}
                                </td>
                                <td>
                                    <a href="{{ route('loans.show', $loan) }}" class="bd-view-btn">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="bd-empty">
                    No loans yet.
                    <a href="{{ route('loans.create', ['borrower_id' => $borrower->id]) }}">Create first loan</a>
                </div>
            @endif
        </div>

    </div>
@endsection
