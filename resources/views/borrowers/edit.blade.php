@extends('layouts.app')
@section('title', 'Edit ' . $borrower->name)
@section('page-title', 'Edit Borrower')
@section('page-subtitle', 'Update borrower information.')

@push('header-actions')
    <a href="{{ route('borrowers.show', $borrower) }}" class="be-btn be-btn-ghost be-btn-sm">← Back</a>
@endpush

@push('styles')
    <style>
        .borrower-edit {
            max-width: 100%;
            margin: 0;
        }

        .borrower-edit .be-card {
            background: #ffffff;
            border: 1px solid #eef0f3;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
            overflow: hidden;
        }

        .borrower-edit .be-fields {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .borrower-edit .be-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 640px) {
            .borrower-edit .be-row {
                grid-template-columns: 1fr;
            }
        }

        .borrower-edit .be-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .borrower-edit .be-required {
            color: #ef4444;
        }

        .borrower-edit .be-input,
        .borrower-edit .be-textarea,
        .borrower-edit .be-select {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            color: #111827;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .borrower-edit .be-input:focus,
        .borrower-edit .be-textarea:focus,
        .borrower-edit .be-select:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
        }

        .borrower-edit .be-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%236b7280' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            padding-right: 36px;
        }

        .borrower-edit .be-error {
            font-size: 12px;
            color: #ef4444;
            margin-top: 4px;
        }

        .borrower-edit .be-username-wrap {
            position: relative;
        }

        .borrower-edit .be-username-at {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 14px;
        }

        .borrower-edit .be-username-wrap .be-input {
            padding-left: 28px;
        }

        .borrower-edit .be-sysinfo {
            border-top: 1px solid #e5e7eb;
            padding-top: 18px;
            margin-top: 4px;
        }

        .borrower-edit .be-sysinfo-title {
            font-size: 12px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 14px;
        }

        .borrower-edit .be-sysinfo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .borrower-edit .be-sysinfo-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .borrower-edit .be-sysinfo-key {
            color: #9ca3af;
            display: block;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .borrower-edit .be-sysinfo-value {
            font-family: 'DM Sans', monospace;
            color: #111827;
            font-size: 14px;
        }

        .borrower-edit .be-sysinfo-value.be-code {
            color: #16a34a;
            font-weight: 600;
        }

        .borrower-edit .be-capitalize {
            text-transform: capitalize;
            font-family: 'DM Sans', sans-serif;
            color: #111827;
            font-weight: 500;
        }

        .borrower-edit .be-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            color: #111827;
            font-size: 14px;
        }

        .borrower-edit .be-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
        }

        .borrower-edit .be-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin: 24px -28px -28px;
            padding: 18px 28px;
            background: #f7f8fb;
            border-top: 1px solid #eef0f3;
        }

        .borrower-edit .be-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            border: none;
            transition: all .15s;
            text-decoration: none;
        }

        .borrower-edit .be-btn-primary {
            background: #22c55e;
            color: #ffffff;
        }

        .borrower-edit .be-btn-primary:hover {
            background: #16a34a;
        }

        .borrower-edit .be-btn-ghost {
            background: #ffffff;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .borrower-edit .be-btn-ghost:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .borrower-edit .be-btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }

        .borrower-edit .be-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 12px;
            padding: 16px 18px;
            margin-top: 20px;
        }

        .borrower-edit .be-notice-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            color: #16a34a;
            margin-top: 1px;
        }

        .borrower-edit .be-notice-title {
            font-size: 14px;
            font-weight: 600;
            color: #15803d;
            margin-bottom: 2px;
        }

        .borrower-edit .be-notice-text {
            font-size: 13px;
            color: #4b5563;
            line-height: 1.5;
        }
    </style>
@endpush

@section('content')
    <div class="borrower-edit">
        <div class="be-card">
            <form method="POST" action="{{ route('borrowers.update', $borrower) }}">
                @csrf
                @method('PATCH')

                <div class="be-fields">

                    <div class="be-row">
                        <div>
                            <label class="be-label">Full Name <span class="be-required">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $borrower->name) }}" class="be-input"
                                required>
                            @error('name')
                            <p class="be-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="be-label">Telegram Username</label>
                            <div class="be-username-wrap">
                                <span class="be-username-at">@</span>
                                <input type="text" name="telegram_username"
                                    value="{{ old('telegram_username', $borrower->telegram_username) }}" class="be-input"
                                    placeholder="username">
                            </div>
                            @error('telegram_username')
                            <p class="be-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="be-row">
                        <div>
                            <label class="be-label">Phone Number</label>
                            <input type="text" name="phone_number"
                                value="{{ old('phone_number', $borrower->phone_number) }}" class="be-input"
                                placeholder="+855 12 345 678">
                            @error('phone_number')
                            <p class="be-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="be-label">Status <span class="be-required">*</span></label>
                            <select name="status" class="be-select">
                                <option value="active" {{ old('status', $borrower->status) === 'active' ? 'selected' : '' }}>
                                    Active</option>
                                <option value="blacklisted" {{ old('status', $borrower->status) === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="be-label">Address</label>
                        <input type="text" name="address" value="{{ old('address', $borrower->address) }}" class="be-input"
                            placeholder="City, Province">
                    </div>

                    <div>
                        <label class="be-label">Notes</label>
                        <textarea name="notes" rows="3" class="be-textarea"
                            placeholder="Optional…">{{ old('notes', $borrower->notes) }}</textarea>
                    </div>

                    {{-- Read-only info --}}
                    <div class="be-sysinfo">
                        <p class="be-sysinfo-title">System Info</p>
                        <div class="be-sysinfo-grid">
                            <div>
                                <span class="be-sysinfo-key">Borrower Code</span>
                                <p class="be-sysinfo-value be-code">{{ $borrower->borrower_code ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="be-sysinfo-key">Telegram ID</span>
                                <p class="be-sysinfo-value">{{ $borrower->telegram_user_id ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="be-sysinfo-key">Verification</span>
                                @php $vb = $borrower->verification_badge; @endphp
                                <p class="be-badge"><span class="be-badge-dot"></span>{{ $vb['label'] }}</p>
                            </div>
                            <div>
                                <span class="be-sysinfo-key">Source</span>
                                <p class="be-capitalize">{{ str_replace('_', ' ', $borrower->onboarding_source) }}</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="be-actions">
                    <a href="{{ route('borrowers.show', $borrower) }}" class="be-btn be-btn-ghost">Cancel</a>
                    <button type="submit" class="be-btn be-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="be-notice">
            <svg class="be-notice-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
                <p class="be-notice-title">Security Recommendation</p>
                <p class="be-notice-text">Changing the Telegram ID or Borrower Code may affect existing integrations and
                    automated notifications for this user.</p>
            </div>
        </div>
    </div>
@endsection
