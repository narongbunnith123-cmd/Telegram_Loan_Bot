@extends('layouts.app')
@section('title', 'Edit Loan #' . $loan->id)
@section('page-title', 'Edit Loan #' . $loan->id)
@section('page-subtitle', 'Update loan details and terms below.')

@push('header-actions')
    <a href="{{ route('loans.show', $loan) }}" class="elp-btn elp-btn-ghost elp-btn-sm">← Back</a>
@endpush

@push('styles')
    <style>
        .edit-loan-page {
            width: 100%;
        }

        .edit-loan-page .elp-card {
            background: #f8fafc;
            border: 1px solid #e6e9f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
        }

        .edit-loan-page .elp-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1f2430;
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e5ec;
        }

        .edit-loan-page .elp-section + .elp-section {
            margin-top: 28px;
        }

        .edit-loan-page .elp-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .edit-loan-page .elp-grid {
                grid-template-columns: 1fr 1fr;
            }

            .edit-loan-page .elp-col-span-2 {
                grid-column: span 2 / span 2;
            }
        }

        .edit-loan-page .elp-form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #47494e;
            margin-bottom: 6px;
        }

        .edit-loan-page .elp-required {
            color: #f87171;
        }

        .edit-loan-page .elp-form-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 9px 14px;
            color: #111827;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color .15s;
        }

        .edit-loan-page .elp-form-input:focus {
            border-color: #22c55e;
        }

        .edit-loan-page .elp-form-select {
            appearance: none;
            background-color: #ffffff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%236b7280' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            padding-right: 36px;
        }

        .edit-loan-page .elp-input-prefix {
            position: relative;
        }

        .edit-loan-page .elp-input-prefix span {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 14px;
            pointer-events: none;
        }

        .edit-loan-page .elp-input-prefix input {
            padding-left: 28px;
        }

        .edit-loan-page .elp-error {
            font-size: 12px;
            color: #f87171;
            margin-top: 4px;
        }

        .edit-loan-page .elp-inline-flex {
            display: flex;
            gap: 8px;
        }

        .edit-loan-page .elp-balance-box {
            margin-top: 28px;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #dbe3f0;
            background: #eef2fb;
        }

        .edit-loan-page .elp-balance-title {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-bottom: 8px;
        }

        .edit-loan-page .elp-balance-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 2px 0;
        }

        .edit-loan-page .elp-text-muted {
            color: #6b7280;
        }

        .edit-loan-page .elp-text-red {
            color: #ef4444;
            font-weight: 600;
        }

        .edit-loan-page .elp-text-green {
            color: #16a34a;
            font-weight: 600;
        }

        .edit-loan-page .elp-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .edit-loan-page .elp-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            border: none;
            transition: all .15s;
            text-decoration: none;
        }

        .edit-loan-page .elp-btn-primary {
            background: #16a34a;
            color: #ffffff;
            flex: 1;
        }

        .edit-loan-page .elp-btn-primary:hover {
            background: #15803d;
        }

        .edit-loan-page .elp-btn-ghost {
            background: #ffffff;
            color: #374151;
            border: 1px solid #e5e7eb;
            font-weight: 500;
        }

        .edit-loan-page .elp-btn-ghost:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .edit-loan-page .elp-btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }
    </style>
@endpush

@section('content')
    <div class="edit-loan-page">
        <div class="elp-card">
            <form method="POST" action="{{ route('loans.update', $loan) }}">
                @csrf
                @method('PATCH')

                {{-- Loan Identity --}}
                <div class="elp-section">
                    <div class="elp-section-title">Loan Identity</div>

                    <div class="elp-grid">

                        {{-- Group --}}
                        <div>
                            <label class="elp-form-label">Telegram Group <span class="elp-required">*</span></label>
                            <select name="group_id" id="group_id" class="elp-form-input elp-form-select" required>
                                <option value="">Select group…</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ old('group_id', $loan->group_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('group_id')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Borrower --}}
                        <div>
                            <label class="elp-form-label">Borrower <span class="elp-required">*</span></label>
                            <select name="borrower_id" id="borrower_id" class="elp-form-input elp-form-select" required>
                                <option value="">Select borrower…</option>
                                @foreach($borrowers as $b)
                                    <option value="{{ $b->id }}" {{ old('borrower_id', $loan->borrower_id) == $b->id ? 'selected' : '' }}>
                                        {{ $b->name }} {{ $b->telegram_username ? '(@' . $b->telegram_username . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('borrower_id')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="elp-form-label">Status <span class="elp-required">*</span></label>
                            <select name="status" class="elp-form-input elp-form-select">
                                @foreach(['active', 'overdue', 'paid', 'completed', 'defaulted', 'cancelled'] as $s)
                                    <option value="{{ $s }}" {{ old('status', $loan->status) === $s ? 'selected' : '' }}>
                                        {{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                            @error('status')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                {{-- Terms & Schedule --}}
                <div class="elp-section">
                    <div class="elp-section-title">Terms &amp; Schedule</div>

                    <div class="elp-grid">

                        {{-- Loan Amount --}}
                        <div>
                            <label class="elp-form-label">Loan Amount <span class="elp-required">*</span></label>
                            <div class="elp-input-prefix">
                                <span>$</span>
                                <input type="number" name="principal" id="field-principal" step="0.01" min="1"
                                    value="{{ old('principal', $loan->principal) }}" class="elp-form-input" required>
                            </div>
                            @error('principal')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Interest --}}
                        <div>
                            <label class="elp-form-label">Interest Rate <span class="elp-required">*</span></label>
                            <div class="elp-inline-flex">
                                <input type="number" name="interest_value" id="field-interest" step="0.01" min="0"
                                    value="{{ old('interest_value', $loan->interest_value) }}" class="elp-form-input" required>
                                <select name="interest_type" id="field-interest-type" class="elp-form-input elp-form-select"
                                    style="width:120px;">
                                    <option value="fixed" {{ old('interest_type', $loan->interest_type) === 'fixed' ? 'selected' : '' }}>$/day</option>
                                    <option value="percentage" {{ old('interest_type', $loan->interest_type) === 'percentage' ? 'selected' : '' }}>%/day</option>
                                </select>
                            </div>
                            @error('interest_value')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Loan Date --}}
                        <div>
                            <label class="elp-form-label">Loan Date <span class="elp-required">*</span></label>
                            <input type="date" name="loan_date" id="field-loan-date"
                                value="{{ old('loan_date', $loan->loan_date->toDateString()) }}" class="elp-form-input"
                                required>
                            @error('loan_date')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Due Date --}}
                        <div>
                            <label class="elp-form-label">Due Date <span class="elp-required">*</span></label>
                            <input type="date" name="due_date" id="field-due-date"
                                value="{{ old('due_date', $loan->due_date->toDateString()) }}" class="elp-form-input" required>
                            @error('due_date')
                            <p class="elp-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Notes --}}
                        <div class="elp-col-span-2">
                            <label class="elp-form-label">Notes</label>
                            <textarea name="notes" rows="3" class="elp-form-input"
                                placeholder="Optional notes…">{{ old('notes', $loan->notes) }}</textarea>
                        </div>

                    </div>
                </div>

                {{-- Current Balance Info --}}
                <div class="elp-balance-box">
                    <div class="elp-balance-title">Current Balance</div>
                    <div>
                        <div class="elp-balance-row">
                            <span class="elp-text-muted">Outstanding Balance</span>
                            <span
                                class="{{ $loan->balance > 0 ? 'elp-text-red' : 'elp-text-green' }}">${{ number_format($loan->balance, 2) }}</span>
                        </div>
                        <div class="elp-balance-row">
                            <span class="elp-text-muted">Total Paid</span>
                            <span
                                class="elp-text-green">${{ number_format($loan->payments->where('status', 'approved')->sum('amount'), 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="elp-actions">
                    <button type="submit" class="elp-btn elp-btn-primary">Save Changes</button>
                    <a href="{{ route('loans.show', $loan) }}" class="elp-btn elp-btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
