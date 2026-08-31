{{-- resources/views/borrowers/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Borrowers')
@section('page-title', 'Borrowers')
@section('page-subtitle', 'View and manage all borrowers.')

@push('header-actions')
    <a href="{{ route('borrowers.create') }}" class="btn btn-primary btn-sm">+ Add Borrower</a>
@endpush

@section('content')
    <div class="card">
        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <input type="text" name="search" value="{{ request('search') }}" class="form-input" style="width:220px;"
                placeholder="Name, phone, code…">
            <select name="status" class="form-input form-select" style="width:140px;">
                <option value="">All Status</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="blacklisted" {{ request('status') === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
            </select>
            <select name="verification" class="form-input form-select" style="width:140px;">
                <option value="">All Telegram</option>
                <option value="linked" {{ request('verification') === 'linked' ? 'selected' : '' }}>🟢 Linked</option>
                <option value="pending" {{ request('verification') === 'pending' ? 'selected' : '' }}>🟡 Pending</option>
                <option value="unlinked" {{ request('verification') === 'unlinked' ? 'selected' : '' }}>⚪ Unlinked</option>
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        </form>
        <div class="table-container1">
            <table class="data-table1">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Telegram</th>
                        <th>Status</th>
                        <th>Active Loans</th>
                        <th>Total Owed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($borrowers as $borrower)
                        <tr>
                            <td>
                                <a href="{{ route('borrowers.show', $borrower) }}"
                                    class="font-600 text-gray-600 hover:text-green-400 transition-colors">
                                    {{ $borrower->name }}
                                </a>
                                @if($borrower->borrower_code)
                                    <span class="text-black font-bold">:</span> (<span
                                        class="text-xs text-gray-500 font-mono ml-1">{{ $borrower->borrower_code }}</span>)
                                @endif
                            </td>
                            <td class="text-sm text-gray-400">{{ $borrower->phone_number ?? '—' }}</td>
                            <td>
                                @php $vb = $borrower->verification_badge; @endphp
                                <span class="inline-flex items-center gap-1 text-sm">
                                    <span>
                                        @if($borrower->verification_status === 'linked')
                                            @include('icons.Linked.Linked', ['width' => 16, 'height' => 16, 'class' => 'text-green-500'])
                                        @elseif($borrower->verification_status === 'pending')
                                            @include('icons.Pendinglinked.Pendinglinked', ['width' => 16, 'height' => 16, 'class' => 'text-yellow-500'])
                                        @else
                                            @include('icons.Unlinked.Unlinked', ['width' => 16, 'height' => 16, 'class' => 'text-gray-400'])
                                        @endif
                                    </span>
                                    @if($borrower->telegram_username)
                                        <span class="text-gray-400">{{ '@' . $borrower->telegram_username }}</span>
                                    @else
                                        <span class="text-gray-500">{{ $vb['label'] }}</span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                <span
                                    class="badge {{ $borrower->status === 'blacklisted' ? 'badge-overdue' : 'badge-active' }}">
                                    {{ ucfirst($borrower->status) }}
                                </span>
                            </td>
                            <td>{{ $borrower->loans->whereIn('status', ['active', 'overdue'])->count() }}</td>
                            <td
                                class="{{ $borrower->loans->whereIn('status', ['active', 'overdue'])->sum('balance') > 0 ? 'text-red-400 font-600' : '' }}">
                                ${{ number_format($borrower->loans->whereIn('status', ['active', 'overdue'])->sum('balance'), 2) }}
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('borrowers.show', $borrower) }}" class="btn btn-ghost btn-sm">View</a>
                                    <a href="{{ route('borrowers.edit', $borrower) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <a href="{{ route('loans.create', ['borrower_id' => $borrower->id]) }}"
                                        class="btn btn-primary btn-sm">New Loan</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-gray-500 py-10">No borrowers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $borrowers->links() }}</div>
    </div>
@endsection
