{{-- resources/views/reminders/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Reminders')
@section('page-title', 'Reminder Log')
@section('page-subtitle', 'View all sent and scheduled reminders.')

@section('content')
    <div class="card">
        <div class="flex gap-3 mb-5">
            <select name="status" onchange="window.location.href='?status='+this.value" class="form-input form-select"
                style="width:140px;">
                <option value="">All</option>
                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            </select>
        </div>
        <div class="table-container">
            <table class="data-table1">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Group</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reminders as $r)
                        <tr>
                            <td class="font-500">{{ $r->loan->borrower->name }}</td>
                            <td class="text-sm text-gray-400">{{ $r->loan->group->name }}</td>
                            <td>
                                <span
                                    class="badge badge-{{ $r->status === 'sent' ? 'active' : ($r->status === 'failed' ? 'overdue' : 'pending') }}">
                                    {{ ucfirst($r->status) }}
                                </span>
                            </td>
                            <td class="text-xs text-gray-400">
                                {{ $r->sent_at?->format('d M Y H:i') ?? $r->scheduled_at->format('d M Y H:i') }}
                            </td>
                            <td class="text-xs text-gray-500 max-w-xs truncate">
                                {{ Str::limit(strip_tags($r->message_snapshot), 80) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-gray-500 py-10">No reminders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reminders->links() }}</div>
    </div>
@endsection