@extends('layouts.app')
@section('title', $group->name)
@section('page-title', $group->name)
@section('page-subtitle', 'View group details, members, and loans.')

@section('breadcrumb')
    <a href="{{ route('groups.index') }}" class="text-blue-500 hover:text-blue-700 hover:underline">Groups</a>
    <span class="text-gray-400 text-xs">›</span>
    <span class="text-gray-500">Group Details</span>
@endsection

@push('header-actions')
    <a href="{{ route('loans.create', ['group_id' => $group->id]) }}" class="btn btn-primary btn-sm">+ New Loan</a>
@endpush

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Group Settings --}}
        <div class="card">
            <h2 class="font-body font-900 text-lg mb-5">Group Settings</h2>
            <form method="POST" action="{{ route('groups.update', $group) }}">
                @csrf @method('PATCH')

                <div class="space-y-4">
                    <div>
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" name="currency" value="{{ $group->settings['currency'] ?? '$' }}"
                            class="form-input" placeholder="$" maxlength="5">
                    </div>

                    <div>
                        <label class="form-label">Default Interest Rate</label>
                        <div class="flex gap-2">
                            <input type="number" name="interest_rate" step="0.01"
                                value="{{ $group->settings['interest_rate'] ?? '' }}" class="form-input" placeholder="2.00">
                            <select name="interest_type" class="form-input form-select" style="width:120px;">
                                <option value="percentage" {{ ($group->settings['interest_type'] ?? '') === 'percentage' ? 'selected' : '' }}>%/day</option>
                                <option value="fixed" {{ ($group->settings['interest_type'] ?? '') === 'fixed' ? 'selected' : '' }}>$/day</option>
                            </select>
                        </div>
                    </div>

                    <div x-data="{
                                        open: false,
                                        selected: '{{ $group->settings['reminder_frequency'] ?? 'daily' }}',
                                        labels: {
                                            off: 'Off',
                                            daily: 'Daily',
                                            twice_daily: 'Twice Daily',
                                            every_6h: 'Every 6 Hours',
                                            every_12h: 'Every 12 Hours',
                                            weekly: 'Weekly (Monday)',
                                        },
                                    }" class="relative">
                        <label class="form-label">Reminder Frequency</label>

                        <input type="hidden" name="reminder_frequency" x-model="selected">

                        <button type="button" @click="open = !open" @click.outside="open = false"
                            class="form-input form-select w-full flex items-center justify-between text-left">
                            <span class="flex items-center gap-2">
                                <template x-if="selected === 'off'">
                                    <span>@include('icons.Off.Off', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])</span>
                                </template>
                                <template x-if="selected === 'daily'">
                                    <span>@include('icons.Daily.Daily', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])</span>
                                </template>
                                <template x-if="selected === 'twice_daily'">
                                    <span>@include('icons.Twice.Twice', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])</span>
                                </template>
                                <template x-if="selected === 'every_6h' || selected === 'every_12h'">
                                    <span>@include('icons.Loop.Loop', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])</span>
                                </template>
                                <template x-if="selected === 'weekly'">
                                    <span>@include('icons.Weekly.Weekly', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])</span>
                                </template>
                                <span x-text="labels[selected]"></span>
                            </span>
                            <svg class="w-3.5 h-3.5 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-show="open" x-transition
                            class="absolute z-10 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden">
                            <div @click="selected = 'off'; open = false"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                @include('icons.Off.Off', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])
                                <span>Off</span>
                            </div>
                            <div @click="selected = 'daily'; open = false"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                @include('icons.Daily.Daily', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])
                                <span>Daily</span>
                            </div>
                            <div @click="selected = 'twice_daily'; open = false"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                @include('icons.Twice.Twice', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])
                                <span>Twice Daily</span>
                            </div>
                            <div @click="selected = 'every_6h'; open = false"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                @include('icons.Loop.Loop', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])
                                <span>Every 6 Hours</span>
                            </div>
                            <div @click="selected = 'every_12h'; open = false"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                @include('icons.Loop.Loop', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])
                                <span>Every 12 Hours</span>
                            </div>
                            <div @click="selected = 'weekly'; open = false"
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                @include('icons.Weekly.Weekly', ['width' => 18, 'height' => 18, 'class' => 'text-gray-500'])
                                <span>Weekly (Monday)</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">
                            <span
                                class="flex flex-wrap gap-1">@include('icons.Reminder.Reminder', ['width' => 17, 'height' => 17, 'class' => 'text-gray-500'])
                                1st Reminder Time</span></label>
                        <input type="time" name="reminder_time_1" value="{{ $group->reminder_time_1 ?? '17:00' }}"
                            class="form-input" style="width:160px;">
                        <p class="text-xs text-gray-800 mt-1">First daily interest reminder (default: 5:00 PM).</p>
                    </div>

                    <div>
                        <label class="form-label"><span
                                class="flex flex-wrap gap-1">@include('icons.Reminder.Reminder', ['width' => 17, 'height' => 17, 'class' => 'text-gray-500'])
                                2nd Reminder Time</span></label>
                        <input type="time" name="reminder_time_2" value="{{ $group->reminder_time_2 ?? '21:00' }}"
                            class="form-input" style="width:160px;">
                        <p class="text-xs text-gray-800 mt-1">Second reminder if still unpaid (default: 9:00 PM).</p>
                    </div>

                    <div>
                        <label class="form-label">Warn Before Due Date (days)</label>
                        <input type="number" name="reminder_days_before" min="0" max="30"
                            value="{{ $group->settings['reminder_days_before'] ?? 3 }}" class="form-input"
                            style="width:120px;">
                        <p class="text-xs text-gray-800 mt-1">Send approaching-due reminders this many days before the due
                            date.</p>
                    </div>

                    <div>
                        <label class="form-label">Late Penalty (extra fixed/day)</label>
                        <input type="number" name="late_penalty" step="0.01"
                            value="{{ $group->settings['late_penalty'] ?? '' }}" class="form-input" placeholder="0.00">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full mt-5 justify-center">Save Settings</button>
            </form>

            <div class="mt-5 pt-5 border-t border-gray-800">
                <div class="text-xs text-gray-700 space-y-2">
                    <div class="flex justify-between"><span>Status</span><span
                            class="badge badge-{{ $group->status }}">{{ ucfirst($group->status) }}</span></div>
                    <div class="flex justify-between"><span>Telegram ID</span><code
                            class="text-green-400">{{ $group->telegram_group_id }}</code></div>
                    <div class="flex justify-between">
                        <span>Joined</span><span>{{ $group->joined_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loans in this group --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="font-body font-900 text-lg">Loans in this Group</h2>
                    <div class="flex gap-2 text-xs">
                        <span class="badge badge-active">{{ $group->loans->where('status', 'active')->count() }}
                            active</span>
                        <span class="badge badge-overdue">{{ $group->loans->where('status', 'overdue')->count() }}
                            overdue</span>
                        <span class="badge badge-paid">{{ $group->loans->where('status', 'paid')->count() }}
                            paid</span>
                    </div>
                </div>
                <div class="table-container1">
                    <table class="data-table1">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Principal</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($group->loans->sortByDesc('created_at') as $loan)
                                <tr>
                                    <td class="font-500">{{ $loan->borrower->name }}</td>
                                    <td>${{ number_format($loan->principal, 2) }}</td>
                                    <td class="{{ $loan->status === 'overdue' ? 'text-red-400 font-600' : '' }}">
                                        ${{ number_format($loan->balance, 2) }}</td>
                                    <td><span
                                            class="badge-solid badge-solid-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span>
                                    </td>
                                    <td class="text-xs text-gray-400">
                                        {{ $loan->due_date ? $loan->due_date->format('d M Y') : 'No end date' }}
                                    </td>
                                    <td><a href="{{ route('loans.show', $loan) }}" class="btn btn-ghost btn-sm">View</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-gray-500 py-8">No loans yet in this group.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Borrowers in this group --}}
            <div class="card">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="font-body font-900 text-lg">Borrowers in this Group</h2>
                    <span class="badge badge-active">{{ $borrowers->count() }} total</span>
                </div>
                <div class="table-container2">
                    <table class="data-table2">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Telegram</th>
                                <th>Status</th>
                                <th>Active Loans</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($borrowers->sortBy('name') as $borrower)
                                <tr>
                                    <td class="font-600">{{ $borrower->name }}</td>
                                    <td class="text-sm text-gray-400">
                                        {{ $borrower->telegram_username ? '@' . $borrower->telegram_username : '—' }}
                                    </td>
                                    <td>
                                        <span
                                            class="badge {{ $borrower->status === 'blacklisted' ? 'badge-overdue' : 'badge-active' }}">
                                            {{ ucfirst($borrower->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $group->loans->where('borrower_id', $borrower->id)->whereIn('status', ['active', 'overdue'])->count() }}
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <a href="{{ route('loans.create', ['group_id' => $group->id, 'borrower_id' => $borrower->id]) }}"
                                                class="btn btn-primary btn-sm">New Loan</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-gray-500 py-8">No borrowers in this group yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
