{{-- resources/views/borrowers/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Add Borrower')
@section('page-title', 'Add Borrower')
@section('page-subtitle', 'Register a new borrower in the system to begin managing their micro-loans and payment history.')

@section('content')
    {{--
    Everything for this page lives under .borrower-create-page.
    All custom classes below are prefixed bc- (borrower-create) so they
    can never collide with .card / .btn / .form-input / etc. from app.blade.php.
    --}}
    <div class="borrower-create-page">
        <div class="bc-wrap">

            {{-- Breadcrumb --}}
            <nav class="bc-breadcrumb">
                <a href="{{ route('borrowers.index') }}">Borrowers</a>
                <span class="bc-breadcrumb-sep">&rsaquo;</span>
                <span class="bc-breadcrumb-current">Add New</span>
            </nav>

            <div class="bc-grid">

                {{-- ===================== LEFT: FORM ===================== --}}
                <div class="bc-card">

                    {{-- Participant Picker --}}
                    <div class="bc-mb-6">
                        <label class="bc-label bc-label-lg">
                            <span class="bc-label-icon">📱</span> Quick Add from Group Members
                        </label>
                        <select id="group-picker" class="bc-input bc-select bc-mb-3">
                            <option value="">Select a group to see members…</option>
                            @foreach($groups ?? [] as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                        <p class="bc-hint">Pulling data from groups can save up to 40% of manual entry time.</p>

                        <div id="participants-list" class="bc-hidden">
                            <div id="participants-loading" class="bc-loading bc-hidden">
                                Loading members…
                            </div>
                            <div id="participants-container" class="bc-participants">
                            </div>
                            <p class="bc-hint">Click a member to auto-fill their info below</p>
                        </div>
                    </div>

                    <div class="bc-divider">
                        <p class="bc-section-label">Borrower Details</p>
                    </div>

                    {{-- Duplicate Warning --}}
                    @if(!empty($duplicates))
                        <div class="bc-warning">
                            <p class="bc-warning-title">⚠️ Potential Duplicate Found</p>
                            @foreach($duplicates as $dup)
                                <div class="bc-warning-row">
                                    <span class="bc-warning-text">{{ $dup['borrower']->name }} — {{ $dup['reason'] }}</span>
                                    <a href="{{ route('borrowers.show', $dup['borrower']) }}" class="bc-warning-link">View</a>
                                </div>
                            @endforeach
                            <p class="bc-warning-note">You can still create if this is a different person.</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('borrowers.store') }}">
                        @csrf
                        @if(!empty($duplicates))
                            <input type="hidden" name="force_create" value="1">
                        @endif

                        <div class="bc-fields">

                            <div>
                                <label class="bc-label">Full Name <span class="bc-required">*</span></label>
                                <div class="bc-input-icon-group">
                                    <svg class="bc-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    <input type="text" name="name" id="field-name" value="{{ old('name') }}"
                                        class="bc-input bc-input-with-icon" placeholder="John Doe" required>
                                </div>
                                @error('name')
                                <p class="bc-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="bc-fields-row">
                                <div>
                                    <label class="bc-label">Telegram Username</label>
                                    <div class="bc-input-group">
                                        <span class="bc-input-prefix">@</span>
                                        <input type="text" name="telegram_username" id="field-username"
                                            value="{{ old('telegram_username') }}" class="bc-input bc-input-with-prefix"
                                            placeholder="username">
                                    </div>
                                    @error('telegram_username')
                                    <p class="bc-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="bc-label">Phone Number</label>
                                    <div class="bc-input-icon-group">
                                        <svg class="bc-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path
                                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
                                        </svg>
                                        <input type="text" name="phone_number" id="field-phone"
                                            value="{{ old('phone_number') }}" class="bc-input bc-input-with-icon"
                                            placeholder="+855 12 345 678">
                                    </div>
                                    @error('phone_number')
                                    <p class="bc-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <input type="hidden" name="telegram_user_id" id="field-telegram-id"
                                value="{{ old('telegram_user_id') }}">

                            <div>
                                <label class="bc-label">Address</label>
                                <div class="bc-input-icon-group">
                                    <svg class="bc-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    <input type="text" name="address" id="field-address" value="{{ old('address') }}"
                                        class="bc-input bc-input-with-icon" placeholder="City, Province, District">
                                </div>
                            </div>

                            <div>
                                <label class="bc-label">Notes</label>
                                <textarea name="notes" id="field-notes" rows="3" class="bc-input"
                                    placeholder="Any additional information regarding credit history or risk assessment…">{{ old('notes') }}</textarea>
                            </div>

                        </div>

                        <div class="bc-actions">
                            <a href="{{ route('borrowers.index') }}" class="bc-btn bc-btn-ghost">Cancel</a>
                            @if(!empty($duplicates))
                                <button type="submit" class="bc-btn bc-btn-primary">Create Anyway</button>
                            @else
                                <button type="submit" class="bc-btn bc-btn-primary">Create Borrower</button>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- ===================== RIGHT: SIDEBAR ===================== --}}
                <div class="bc-sidebar">

                    <div class="bc-side-card">
                        <p class="bc-side-title">Registration Guide</p>
                        <div class="bc-guide-row">
                            <span class="bc-guide-num">01</span>
                            <p class="bc-guide-text">Verify legal identification before confirming the full name to ensure
                                contract validity.</p>
                        </div>
                        <div class="bc-guide-row">
                            <span class="bc-guide-num">02</span>
                            <p class="bc-guide-text">Telegram accounts are required for automated bot reminders and
                                real-time alerts.</p>
                        </div>
                        <div class="bc-guide-row">
                            <span class="bc-guide-num">03</span>
                            <p class="bc-guide-text">Adding a detailed address improves geographic risk analysis accuracy.
                            </p>
                        </div>
                    </div>

                    <div class="bc-side-card bc-trust-card" id="trust-score-card">
                        <div class="bc-trust-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                class="bc-trust-icon">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <path d="M9 12l2 2 4-4" />
                            </svg>
                            <span>Trust Score Prediction</span>
                        </div>
                        <p class="bc-trust-rate">Likely Approval Rate: <span id="trust-rate-value">--</span></p>
                        <div class="bc-trust-bar">
                            <div class="bc-trust-bar-fill" id="trust-bar-fill" style="width:0%"></div>
                        </div>
                        <p class="bc-trust-hint" id="trust-hint">Fill out the fields to generate a preliminary risk
                            assessment.</p>
                    </div>

                    <div class="bc-encryption-panel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            class="bc-encryption-icon">
                            <rect x="3" y="11" width="18" height="11" rx="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <p class="bc-encryption-title">Secure Data Encryption</p>
                        <p class="bc-encryption-text">All personal data is encrypted using AES-256 standards.</p>
                    </div>

                </div>

            </div>
        </div>
    </div>

    @push('styles')
        <style>
            /*
                             * Scoped styles for the Add Borrower page only.
                             * Every rule is nested under .borrower-create-page so it can never
                             * leak out and affect any other view that extends app.blade.php.
                             */
            .borrower-create-page .bc-wrap {
                max-width: 1600px;
                margin-left: auto;
                margin-right: auto;
            }

            .borrower-create-page .bc-breadcrumb {
                font-size: 13px;
                color: #6b7280;
                margin-bottom: 16px;
            }

            .borrower-create-page .bc-breadcrumb a {
                color: #6b7280;
                text-decoration: none;
            }

            .borrower-create-page .bc-breadcrumb a:hover {
                text-decoration: underline;
            }

            .borrower-create-page .bc-breadcrumb-sep {
                margin: 0 6px;
                color: #d1d5db;
            }

            .borrower-create-page .bc-breadcrumb-current {
                color: #16a34a;
                font-weight: 700;
            }

            .borrower-create-page .bc-grid {
                display: grid;
                grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
                gap: 24px;
                align-items: start;
            }

            @media (max-width: 900px) {
                .borrower-create-page .bc-grid {
                    grid-template-columns: 1fr;
                }
            }

            .borrower-create-page .bc-card {
                background: #ffffff;
                border: 1px solid #eef0f3;
                border-radius: 16px;
                padding: 28px;
                box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
            }

            .borrower-create-page .bc-hidden {
                display: none;
            }

            .borrower-create-page .bc-mb-3 {
                margin-bottom: 12px;
            }

            .borrower-create-page .bc-mb-6 {
                margin-bottom: 24px;
            }

            .borrower-create-page .bc-label {
                display: block;
                font-size: 14px;
                font-weight: 500;
                color: #47494e;
                margin-bottom: 6px;
            }

            .borrower-create-page .bc-label-lg {
                font-size: 15px;
                font-weight: 600;
                color: #111827;
            }

            .borrower-create-page .bc-label-icon {
                margin-right: 4px;
            }

            .borrower-create-page .bc-required {
                color: #f87171;
            }

            .borrower-create-page .bc-input {
                width: 100%;
                background-color: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 10px;
                padding: 9px 14px;
                color: #111827;
                font-size: 14px;
                font-family: 'DM Sans', sans-serif;
                outline: none;
                transition: border-color .15s;
            }

            .borrower-create-page .bc-input:focus {
                border-color: #22c55e !important;
            }

            /* Stop the browser's autofill blue/lavender tint from showing on
                       Phone Number / Address (or any field it thinks it recognizes) */
            .borrower-create-page .bc-input:-webkit-autofill,
            .borrower-create-page .bc-input:-webkit-autofill:hover,
            .borrower-create-page .bc-input:-webkit-autofill:focus {
                -webkit-box-shadow: 0 0 0 1000px #ffffff inset;
                -webkit-text-fill-color: #111827;
                transition: background-color 5000s ease-in-out 0s;
            }

            .borrower-create-page .bc-select {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%236b7280' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 10px center;
                background-size: 16px;
                padding-right: 36px;
            }

            .borrower-create-page .bc-loading {
                font-size: 14px;
                color: #6b7280;
                padding: 12px 0;
                text-align: center;
            }

            .borrower-create-page .bc-participants {
                display: flex;
                flex-direction: column;
                gap: 4px;
                max-height: 12rem;
                overflow-y: auto;
                border-radius: 10px;
                border: 1px solid #e5e7eb;
                padding: 8px;
            }

            .borrower-create-page .bc-hint {
                font-size: 12px;
                color: #6b7280;
                margin-top: 8px;
            }

            .borrower-create-page .bc-participant-card {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 12px;
                border-radius: 10px;
                cursor: pointer;
                transition: background-color .15s;
            }

            .borrower-create-page .bc-participant-card:hover {
                background: #f3f4f6;
            }

            .borrower-create-page .bc-participant-selected {
                background: rgba(34, 197, 94, 0.12);
                box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.4);
            }

            .borrower-create-page .bc-divider {
                border-top: 1px solid #e5e7eb;
                padding-top: 20px;
                margin-bottom: 8px;
            }

            .borrower-create-page .bc-section-label {
                font-size: 12px;
                font-weight: 600;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                margin-bottom: 16px;
            }

            .borrower-create-page .bc-warning {
                background: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 10px;
                padding: 16px;
                margin-bottom: 20px;
            }

            .borrower-create-page .bc-warning-title {
                font-size: 14px;
                font-weight: 600;
                color: #92400e;
                margin-bottom: 8px;
            }

            .borrower-create-page .bc-warning-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 14px;
                margin-bottom: 4px;
            }

            .borrower-create-page .bc-warning-text {
                color: #374151;
            }

            .borrower-create-page .bc-warning-link {
                color: #16a34a;
                font-size: 12px;
                font-weight: 600;
                text-decoration: none;
            }

            .borrower-create-page .bc-warning-link:hover {
                text-decoration: underline;
            }

            .borrower-create-page .bc-warning-note {
                font-size: 12px;
                color: #92400e;
                margin-top: 8px;
            }

            .borrower-create-page .bc-fields {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .borrower-create-page .bc-fields-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            @media (max-width: 640px) {
                .borrower-create-page .bc-fields-row {
                    grid-template-columns: 1fr;
                }
            }

            .borrower-create-page .bc-error {
                font-size: 12px;
                color: #f87171;
                margin-top: 4px;
            }

            .borrower-create-page .bc-input-group,
            .borrower-create-page .bc-input-icon-group {
                position: relative;
            }

            .borrower-create-page .bc-input-prefix {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #6b7280;
                font-size: 14px;
                z-index: 1;
            }

            .borrower-create-page .bc-input-with-prefix {
                padding-left: 28px;
            }

            .borrower-create-page .bc-field-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                width: 16px;
                height: 16px;
                color: #9ca3af;
                pointer-events: none;
            }

            .borrower-create-page .bc-input-with-icon {
                padding-left: 36px;
            }

            .borrower-create-page .bc-actions {
                display: flex;
                gap: 12px;
                margin-top: 24px;
            }

            .borrower-create-page .bc-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
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
                flex: 1;
            }

            .borrower-create-page .bc-btn-primary {
                background: #16a34a;
                color: #ffffff;
            }

            .borrower-create-page .bc-btn-primary:hover {
                background: #15803d;
            }

            .borrower-create-page .bc-btn-ghost {
                background: #ffffff;
                color: #374151;
                border: 1px solid #e5e7eb;
                flex: none;
            }

            .borrower-create-page .bc-btn-ghost:hover {
                background: #f9fafb;
                border-color: #d1d5db;
            }

            /* ---------- Sidebar ---------- */

            .borrower-create-page .bc-sidebar {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .borrower-create-page .bc-side-card {
                background: #ffffff;
                border: 1px solid #eef0f3;
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
            }

            .borrower-create-page .bc-side-title {
                font-size: 16px;
                font-weight: 700;
                color: #111827;
                margin-bottom: 16px;
            }

            .borrower-create-page .bc-guide-row {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 16px;
            }

            .borrower-create-page .bc-guide-row:last-child {
                margin-bottom: 0;
            }

            .borrower-create-page .bc-guide-num {
                flex: none;
                width: 26px;
                height: 26px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #dcfce7;
                color: #16a34a;
                font-size: 12px;
                font-weight: 700;
                border-radius: 999px;
            }

            .borrower-create-page .bc-guide-text {
                font-size: 13px;
                color: #4b5563;
                line-height: 1.5;
            }

            .borrower-create-page .bc-trust-card {
                border-left: 4px solid #16a34a;
            }

            .borrower-create-page .bc-trust-header {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                font-weight: 700;
                color: #16a34a;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 12px;
            }

            .borrower-create-page .bc-trust-icon {
                width: 18px;
                height: 18px;
            }

            .borrower-create-page .bc-trust-rate {
                font-size: 18px;
                font-weight: 700;
                color: #111827;
                margin-bottom: 10px;
            }

            .borrower-create-page .bc-trust-bar {
                width: 100%;
                height: 6px;
                background: #f3f4f6;
                border-radius: 999px;
                overflow: hidden;
                margin-bottom: 10px;
            }

            .borrower-create-page .bc-trust-bar-fill {
                height: 100%;
                background: #16a34a;
                border-radius: 999px;
                transition: width .3s ease;
            }

            .borrower-create-page .bc-trust-hint {
                font-size: 12px;
                color: #6b7280;
                line-height: 1.5;
            }

            .borrower-create-page .bc-encryption-panel {
                background: linear-gradient(160deg, #1f2937 0%, #111827 100%);
                border-radius: 16px;
                padding: 20px;
                color: #ffffff;
                position: relative;
                overflow: hidden;
            }

            .borrower-create-page .bc-encryption-panel::before {
                content: "";
                position: absolute;
                inset: 0;
                background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
                background-size: 14px 14px;
                pointer-events: none;
            }

            .borrower-create-page .bc-encryption-icon {
                width: 22px;
                height: 22px;
                color: #4ade80;
                margin-bottom: 10px;
                position: relative;
            }

            .borrower-create-page .bc-encryption-title {
                font-size: 14px;
                font-weight: 700;
                margin-bottom: 4px;
                position: relative;
            }

            .borrower-create-page .bc-encryption-text {
                font-size: 12px;
                color: #9ca3af;
                line-height: 1.5;
                position: relative;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            const groupPicker = document.getElementById('group-picker');
            const participantsList = document.getElementById('participants-list');
            const participantsContainer = document.getElementById('participants-container');
            const participantsLoading = document.getElementById('participants-loading');

            groupPicker.addEventListener('change', async function () {
                const groupId = this.value;
                if (!groupId) {
                    participantsList.classList.add('bc-hidden');
                    return;
                }

                participantsList.classList.remove('bc-hidden');
                participantsLoading.classList.remove('bc-hidden');
                participantsContainer.innerHTML = '';

                try {
                    const response = await fetch(`/api/groups/${groupId}/participants`);
                    const participants = await response.json();

                    participantsLoading.classList.add('bc-hidden');

                    if (participants.length === 0) {
                        participantsContainer.innerHTML = '<p class="bc-hint" style="text-align:center;">No members tracked yet. Members appear after they send a message.</p>';
                        return;
                    }

                    participants.forEach(p => {
                        const card = document.createElement('div');
                        card.className = 'bc-participant-card';
                        card.innerHTML = `
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-size:18px;">👤</span>
                                                <div>
                                                    <div style="font-size:14px; font-weight:500; color:#111827;">${p.name}</div>
                                                    <div style="font-size:12px; color:#6b7280;">${p.username ? '@' + p.username : 'No username'}</div>
                                                </div>
                                            </div>
                                            <span style="font-size:12px; color:#6b7280;">${p.last_seen || ''}</span>
                                        `;

                        card.addEventListener('click', () => {
                            document.getElementById('field-name').value = p.name;
                            document.getElementById('field-username').value = p.username || '';
                            document.getElementById('field-telegram-id').value = p.telegram_user_id || '';

                            // Visual feedback
                            document.querySelectorAll('#participants-container > div').forEach(el => {
                                el.classList.remove('bc-participant-selected');
                            });
                            card.classList.add('bc-participant-selected');

                            updateTrustScore();
                        });

                        participantsContainer.appendChild(card);
                    });
                } catch (err) {
                    participantsLoading.classList.add('bc-hidden');
                    participantsContainer.innerHTML = '<p class="bc-hint" style="text-align:center;">Failed to load participants</p>';
                }
            });

            // ---------- Trust Score Prediction (live, calls the server) ----------
            const trustScoreUrl = "{{ route('borrowers.trust-score-preview') }}";
            const trustRateEl = document.getElementById('trust-rate-value');
            const trustBarEl = document.getElementById('trust-bar-fill');
            const trustHintEl = document.getElementById('trust-hint');
            const trustFieldIds = ['field-name', 'field-username', 'field-phone', 'field-address', 'field-notes'];
            let trustDebounce;

            function getCsrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) return meta.content;
                const hidden = document.querySelector('input[name="_token"]');
                return hidden ? hidden.value : '';
            }

            async function updateTrustScore() {
                clearTimeout(trustDebounce);
                trustDebounce = setTimeout(async () => {
                    const payload = {
                        name: document.getElementById('field-name').value,
                        telegram_username: document.getElementById('field-username').value,
                        phone_number: document.getElementById('field-phone').value,
                        address: document.getElementById('field-address').value,
                        notes: document.getElementById('field-notes').value,
                    };

                    try {
                        const response = await fetch(trustScoreUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken(),
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) throw new Error('Request failed');
                        const data = await response.json();

                        if (data.rate > 0) {
                            trustRateEl.textContent = data.rate + '%';
                            trustBarEl.style.width = data.rate + '%';
                            trustHintEl.textContent = data.label;
                        } else {
                            trustRateEl.textContent = '--';
                            trustBarEl.style.width = '0%';
                            trustHintEl.textContent = 'Fill out the fields to generate a preliminary risk assessment.';
                        }
                    } catch (err) {
                        trustHintEl.textContent = 'Unable to calculate right now.';
                    }
                }, 400);
            }

            trustFieldIds.forEach(id => {
                document.getElementById(id).addEventListener('input', updateTrustScore);
            });
        </script>
    @endpush
@endsection
