@extends('layouts.app')
@section('title', 'Create Loan')
@section('page-title', 'Create New Loan')
@section('page-subtitle', 'Fill in the details below to create and issue a new loan.')

@push('styles')
<style>

    .loan-create-page {
        width: 100%;
        margin: 0;
    }

    /* ---------- Breadcrumb ---------- */
    .loan-create-page .lc-breadcrumb {
        font-size: 13px;
        color: #6b7280;
        font-family: 'DM Sans', sans-serif;
        margin-bottom: 16px;
    }

    .loan-create-page .lc-breadcrumb a {
        color: #22c55e;
        font-weight: 600;
        text-decoration: none;
    }

    .loan-create-page .lc-breadcrumb a:hover {
        text-decoration: underline;
    }

    /* ---------- Card ---------- */
    .loan-create-page .lc-card {
        background: #ffffff;
        border: 1px solid #eef0f3;
        border-radius: 16px;
        padding: 32px;
        box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
    }

    /* ---------- Section headers ---------- */
    .loan-create-page .lc-section-title {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        font-family: 'DM Sans', sans-serif;
        margin-bottom: 10px;
    }

    .loan-create-page .lc-section:not(:first-child) .lc-section-title {
        margin-top: 8px;
    }

    .loan-create-page .lc-section-divider {
        border-top: 1px solid #e5e7eb;
        margin-bottom: 20px;
    }

    .loan-create-page .lc-section {
        margin-bottom: 28px;
    }

    .loan-create-page .lc-section:last-of-type {
        margin-bottom: 0;
    }

    /* ---------- Fields ---------- */
    .loan-create-page .lc-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .loan-create-page .lc-input {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 9px 14px;
        color: #111827;
        font-size: 14px;
        font-family: 'DM Sans', sans-serif;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }

    .loan-create-page .lc-input:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
    }

    .loan-create-page .lc-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%236b7280' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        padding-right: 36px;
    }

    /* ---------- Prefix / suffix affixes ($, %) ---------- */
    .loan-create-page .lc-affix-wrap {
        position: relative;
    }

    .loan-create-page .lc-affix-wrap .lc-input {
        padding-left: 26px;
    }

    .loan-create-page .lc-affix-wrap.suffix .lc-input {
        padding-left: 14px;
        padding-right: 30px;
    }

    .loan-create-page .lc-affix {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
        color: #9ca3af;
        pointer-events: none;
    }

    .loan-create-page .lc-affix.prefix { left: 14px; }
    .loan-create-page .lc-affix.suffix { right: 14px; }

    /* ---------- Buttons ---------- */
    .loan-create-page .lc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        border: none;
        transition: all .15s;
        text-decoration: none;
    }

    .loan-create-page .lc-btn-primary {
        background: #22c55e;
        color: #ffffff;
    }

    .loan-create-page .lc-btn-primary:hover {
        background: #16a34a;
    }

    .loan-create-page .lc-btn-ghost {
        background: #ffffff;
        color: #374151;
        border: 1px solid #e5e7eb;
    }

    .loan-create-page .lc-btn-ghost:hover {
        background: #f9fafb;
        border-color: #d1d5db;
    }

    .loan-create-page .lc-error {
        font-size: 12px;
        color: #f87171;
        margin-top: 4px;
    }

    /* ---------- Loan type radio cards ---------- */
    .loan-create-page .lc-radio-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #fafafa;
        cursor: pointer;
        transition: border-color .15s, background .15s;
    }

    .loan-create-page .lc-radio-option:hover {
        border-color: #22c55e;
    }

    .loan-create-page .lc-radio-option:has(:checked) {
        border-color: #22c55e;
        background: rgba(34, 197, 94, 0.06);
    }

    .loan-create-page .lc-radio-option input[type="radio"] {
        margin-top: 2px;
        width: 16px;
        height: 16px;
        accent-color: #22c55e;
    }

    .loan-create-page .lc-radio-option .lc-radio-title {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
    }

    .loan-create-page .lc-radio-option .lc-radio-sub {
        font-size: 12px;
        color: #6b7280;
        margin-top: 1px;
    }

    /* ---------- Penalty panel ---------- */
    .loan-create-page .lc-panel-outer {
        background: #f6f7fb;
        border-top: 1px solid #eef0f3;
        margin: 28px -32px -32px;
        padding: 24px 32px 32px;
        border-radius: 0 0 16px 16px;
    }

    .loan-create-page .lc-panel-heading {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #b45309;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 16px;
    }

    /* ---------- Live preview (kept dark, unchanged behaviour) ---------- */
    .loan-create-page .lc-preview {
        margin-top: 20px;
        border-radius: 10px;
        border: 1px solid #374151;
        background: #111827;
        overflow: hidden;
    }

    .loan-create-page .lc-preview-header {
        padding: 12px 16px;
        background: #1f2937;
        border-bottom: 1px solid #374151;
        font-size: 12px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .loan-create-page .lc-preview-body {
        padding: 16px;
    }

    .loan-create-page .lc-preview-row {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        padding: 4px 0;
    }

    .loan-create-page .lc-preview-divider {
        border-top: 1px solid #374151;
        margin: 8px 0;
    }
</style>
@endpush

@section('content')
<div class="loan-create-page">

    {{-- Breadcrumb --}}
    <div class="lc-breadcrumb">
        <a href="{{ route('loans.index') }}">Loans</a> &gt; <a href="{{ route('loans.create') }}">Add new</a>
    </div>

    <div class="lc-card">
        <form method="POST" action="{{ route('loans.store') }}">
            @csrf

            {{-- ===================== SECTION: LOAN BASICS ===================== --}}
            <div class="lc-section">
                <div class="lc-section-title">Loan Basics</div>
                <div class="lc-section-divider"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Group --}}
                    <div>
                        <label class="lc-label">Telegram Group <span class="text-red-400">*</span></label>
                        <select name="group_id" id="group_id" class="lc-input lc-select" required>
                            <option value="">Select group…</option>
                            @foreach($groups as $group)
                            <option value="{{ $group->id }}" {{ (request('group_id') == $group->id || old('group_id') == $group->id) ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('group_id') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Borrower --}}
                    <div>
                        <label class="lc-label">Borrower <span class="text-red-400">*</span></label>
                        <select name="borrower_id" id="borrower_id" class="lc-input lc-select" required>
                            <option value="">Select borrower…</option>
                            @foreach($borrowers as $b)
                            <option value="{{ $b->id }}" {{ old('borrower_id', request('borrower_id')) == $b->id ? 'selected' : '' }}>
                                {{ $b->name }} {{ $b->telegram_username ? '(@'.$b->telegram_username.')' : '' }}
                            </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Can't find borrower? <a href="{{ route('borrowers.create') }}" class="text-green-500 hover:underline">Add new borrower</a></p>
                        @error('borrower_id') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Loan Type Toggle --}}
                    <div class="md:col-span-2">
                        <label class="lc-label">Loan Type <span class="text-red-400">*</span></label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="lc-radio-option">
                                <input type="radio" name="loan_type" value="lump_sum" id="type-lumpsum"
                                       {{ old('loan_type', 'lump_sum') === 'lump_sum' ? 'checked' : '' }}>
                                <div>
                                    <div class="lc-radio-title">Lump Sum</div>
                                    <div class="lc-radio-sub">Single due date</div>
                                </div>
                            </label>
                            <label class="lc-radio-option">
                                <input type="radio" name="loan_type" value="installment" id="type-installment"
                                       {{ old('loan_type') === 'installment' ? 'checked' : '' }}>
                                <div>
                                    <div class="lc-radio-title">Installment</div>
                                    <div class="lc-radio-sub">Monthly payments</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== SECTION: TERMS ===================== --}}
            <div class="lc-section">
                <div class="lc-section-title">Terms</div>
                <div class="lc-section-divider"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Loan Amount --}}
                    <div>
                        <label class="lc-label">Loan Amount <span class="text-red-400">*</span></label>
                        <div class="lc-affix-wrap">
                            <span class="lc-affix prefix">$</span>
                            <input type="number" name="principal" id="field-principal" step="0.01" min="1"
                                   value="{{ old('principal') }}" class="lc-input" placeholder="500.00" required>
                        </div>
                        @error('principal') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Interest --}}
                    <div>
                        <label class="lc-label">Interest Rate <span class="text-red-400">*</span></label>
                        <div class="flex gap-2">
                            <div class="lc-affix-wrap suffix flex-1">
                                <input type="number" name="interest_value" id="field-interest" step="0.01" min="0"
                                       value="{{ old('interest_value') }}" class="lc-input" placeholder="2.00" required>
                                <span class="lc-affix suffix">%</span>
                            </div>
                            <select name="interest_type" id="field-interest-type" class="lc-input lc-select" style="width:120px;">
                                <option value="fixed"      {{ old('interest_type') === 'fixed'      ? 'selected' : '' }}>$/day</option>
                                <option value="percentage" {{ old('interest_type') === 'percentage' ? 'selected' : '' }}>%/day</option>
                            </select>
                        </div>
                        @error('interest_value') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- ===================== SECTION: SCHEDULE ===================== --}}
            <div class="lc-section">
                <div class="lc-section-title">Schedule</div>
                <div class="lc-section-divider"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Loan Date --}}
                    <div>
                        <label class="lc-label">Loan Date <span class="text-red-400">*</span></label>
                        <input type="date" name="loan_date" id="field-loan-date" value="{{ old('loan_date', today()->toDateString()) }}"
                               class="lc-input" required>
                        @error('loan_date') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Due Date (Lump Sum Only) --}}
                    <div id="section-due-date">
                        <label class="lc-label">Due Date <span class="text-red-400">*</span></label>
                        <input type="date" name="due_date" id="field-due-date" value="{{ old('due_date') }}" class="lc-input">
                        @error('due_date') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Duration Months (Installment Only) --}}
                    <div id="section-duration" class="hidden">
                        <label class="lc-label">Duration (Months) <span class="text-red-400">*</span></label>
                        <input type="number" name="duration_months" id="field-duration" min="1" max="60"
                               value="{{ old('duration_months', 12) }}" class="lc-input" placeholder="12">
                        @error('duration_months') <p class="lc-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Live Loan Summary Preview --}}
            <div id="loan-preview" class="lc-preview hidden">
                <div class="lc-preview-header">📊 Loan Preview</div>
                <div class="lc-preview-body">
                    <div class="lc-preview-row">
                        <span class="text-gray-400">Principal</span>
                        <span id="p-principal" class="font-600 text-white">—</span>
                    </div>
                    <div class="lc-preview-row" id="p-row-duration">
                        <span class="text-gray-400">Duration</span>
                        <span id="p-duration" class="font-600 text-white">—</span>
                    </div>
                    <div class="lc-preview-row" id="p-row-monthly" style="display:none;">
                        <span class="text-gray-400">Monthly Installment</span>
                        <span id="p-monthly" class="font-600 text-blue-400">—</span>
                    </div>
                    <div class="lc-preview-row">
                        <span class="text-gray-400">Daily Interest</span>
                        <span id="p-daily" class="font-600 text-yellow-400">—</span>
                    </div>
                    <div class="lc-preview-divider"></div>
                    <div class="lc-preview-row">
                        <span class="text-gray-400">Total Interest</span>
                        <span id="p-total-interest" class="font-600 text-yellow-400">—</span>
                    </div>
                    <div class="lc-preview-row" id="p-row-penalty" style="display:none;">
                        <span class="text-gray-400">Penalty Rate</span>
                        <span id="p-penalty" class="font-600 text-red-400">—</span>
                    </div>
                    <div class="lc-preview-row text-base">
                        <span class="text-gray-300 font-600">Estimated Total Repayment</span>
                        <span id="p-total" class="font-700 text-green-400">—</span>
                    </div>
                </div>
            </div>

            {{-- ===================== PANEL: PENALTY + NOTES + ACTIONS ===================== --}}
            <div class="lc-panel-outer">

                {{-- Penalty Configuration --}}
                <div class="lc-panel-heading">⚠️ Late Payment Penalty</div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div>
                        <label class="lc-label">Penalty Type</label>
                        <select name="penalty_type" id="field-penalty-type" class="lc-input lc-select">
                            <option value="none"       {{ old('penalty_type', 'none') === 'none'       ? 'selected' : '' }}>No Penalty</option>
                            <option value="fixed"      {{ old('penalty_type') === 'fixed'      ? 'selected' : '' }}>Fixed ($/day)</option>
                            <option value="percentage" {{ old('penalty_type') === 'percentage' ? 'selected' : '' }}>Percentage (%/day)</option>
                        </select>
                    </div>
                    <div id="section-penalty-value" class="hidden">
                        <label class="lc-label">Penalty Value</label>
                        <input type="number" name="penalty_value" id="field-penalty-value" step="0.01" min="0"
                               value="{{ old('penalty_value') }}" class="lc-input" placeholder="2.00">
                    </div>
                    <div id="section-grace-days" class="hidden">
                        <label class="lc-label">Grace Days</label>
                        <input type="number" name="grace_days" id="field-grace-days" min="0" max="30"
                               value="{{ old('grace_days', 3) }}" class="lc-input" placeholder="3">
                        <p class="text-xs text-gray-500 mt-1">Days before penalty starts</p>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mb-6">
                    <label class="lc-label">Notes</label>
                    <textarea name="notes" rows="3" class="lc-input" placeholder="Optional notes about this loan…">{{ old('notes') }}</textarea>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3">
                    <a href="{{ route('loans.index') }}" class="lc-btn lc-btn-ghost">Cancel</a>
                    <button type="submit" class="lc-btn lc-btn-primary">Create Loan</button>
                </div>
            </div>

        </form>
    </div>
</div>

@push('scripts')
<script>
    {{-- your existing JS block stays exactly the same, no changes needed --}}
</script>
@endpush
@endsection
