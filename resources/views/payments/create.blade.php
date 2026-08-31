{{-- resources/views/payments/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Record Payment')
@section('page-title', 'Record Payment')
@section('page-subtitle', 'Record a new payment for a loan.')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="card">
        @if($loan)
        <div class="p-4 rounded-lg mb-5" style="background:#1c2a1c;border:1px solid #2ea04344;">
            <div class="text-xs text-gray-500 mb-2">Recording payment for</div>
            <div class="font-600">{{ $loan->borrower->name }}</div>
            <div class="text-sm text-gray-400">{{ $loan->group->name }}</div>
            <div class="flex items-center gap-4 mt-2 text-sm">
                <span>Balance: <strong class="text-red-400">${{ number_format($loan->balance, 2) }}</strong></span>
                <span class="badge badge-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span>
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="loan_id" value="{{ $loan?->id ?? old('loan_id') }}">

            <div class="space-y-4">

                @if(!$loan)
                <div>
                    <label class="form-label">Loan</label>
                    <select name="loan_id" class="form-input form-select" required>
                        <option value="">Select loan…</option>
                        @foreach($activeLoans as $l)
                        <option value="{{ $l->id }}">{{ $l->borrower->name }} — ${{ number_format($l->balance,2) }} ({{ $l->status }})</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label class="form-label">Amount Paid <span class="text-red-400">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           value="{{ old('amount', $loan?->balance) }}"
                           class="form-input" placeholder="0.00" required>
                    @error('amount') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Payment Type</label>
                    <select name="type" class="form-input form-select">
                        <option value="partial" {{ old('type') === 'partial' ? 'selected' : '' }}>Partial Payment</option>
                        <option value="full"    {{ old('type') === 'full'    ? 'selected' : '' }}>Full Payment</option>
                        <option value="advance" {{ old('type') === 'advance' ? 'selected' : '' }}>Advance Payment</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Payment Method</label>
                    <select name="method" class="form-input form-select">
                        <option value="">— None —</option>
                        <option value="cash"         {{ old('method') === 'cash'         ? 'selected' : '' }}>Cash</option>
                        <option value="bank_transfer" {{ old('method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="crypto"        {{ old('method') === 'crypto'        ? 'selected' : '' }}>Crypto</option>
                        <option value="other"         {{ old('method') === 'other'         ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Payment Proof (optional)</label>
                    <div id="drop-zone" class="border-2 border-dashed border-gray-700 rounded-xl p-6 text-center cursor-pointer hover:border-green-500 transition-colors">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#8b949e" stroke-width="1.5" class="w-8 h-8 mx-auto mb-2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <p class="text-sm text-gray-500">Drop image/screenshot here or <span class="text-green-500">click to browse</span></p>
                        <input type="file" name="proof_file" id="proof-file" accept="image/*,.pdf" class="hidden">
                    </div>
                    <div id="file-name" class="text-xs text-green-400 mt-1 hidden"></div>
                </div>

                <div>
                    <label class="form-label">Notes</label>
                    <textarea name="notes" rows="2" class="form-input" placeholder="Optional notes…">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="btn btn-primary flex-1 justify-center">Record Payment</button>
                <a href="{{ url()->previous() }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('proof-file');
    const fileName  = document.getElementById('file-name');

    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) {
            fileName.textContent = '✓ ' + fileInput.files[0].name;
            fileName.classList.remove('hidden');
        }
    });
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor='#22c55e'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor=''; });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.style.borderColor='';
        fileInput.files = e.dataTransfer.files;
        if (fileInput.files[0]) {
            fileName.textContent = '✓ ' + fileInput.files[0].name;
            fileName.classList.remove('hidden');
        }
    });
</script>
@endpush
@endsection
