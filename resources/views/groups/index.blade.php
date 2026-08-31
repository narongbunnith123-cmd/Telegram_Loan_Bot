@extends('layouts.app')
@section('title', 'Groups')
@section('page-title', 'Telegram Groups')
@section('page-subtitle', 'View and manage your Telegram groups.')

@section('content')

    @php
        $statsSource = $groupStats ?? [
            'total' => $groups->total() ?? $groups->count(),
            'active' => $groups->where('status', 'active')->count(),
            'pending' => $groups->where('status', 'pending')->count(),
            'suspended' => $groups->where('status', 'suspended')->count(),
        ];

        $hasFilters = request()->filled('status') || request()->filled('search');
    @endphp

    {{-- Stats row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-600">Total Groups</span>
                <div class="icon-box" style="background:#3b82f6;">
                    @include('icons.Group.Groups', ['width' => 20, 'height' => 20, ' class' => 'text-white'])
                </div>
            </div>
            <div class="text-3xl font-display font-800 text-gray-900">{{ $statsSource['total'] }}</div>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-600">Active</span>
                <div class="icon-box" style="background:#009000;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" class="w-5 h-5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-display font-800 text-gray-900">{{ $statsSource['active'] }}</div>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-600">Pending</span>
                <div class="icon-box" style="background:#d97706;">
                    <!-- <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" class="w-5 h-5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> -->
                    @include('icons.Pending.Pending', ['width' => 25, 'height' => 25, 'class' => 'text-white'])
                </div>
            </div>
            <div class="text-3xl font-display font-800 text-gray-900">{{ $statsSource['pending'] }}</div>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xl font-bold text-gray-600">Suspended</span>
                <div class="icon-box" style="background:#942222;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" class="w-5 h-5">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-display font-800 text-gray-900">{{ $statsSource['suspended'] }}</div>
        </div>
    </div>

    <div class="card">
        <div class="table-toolbar">
            <p class="text-sm font-sans text-gray-700">Groups are registered automatically when your bot joins them.</p>

            <div class="flex items-center gap-2 flex-wrap">
                <form method="GET" class="table-search-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}" class="table-search"
                        placeholder="Search groups...">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                </form>

                <form method="GET">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <select name="status" onchange="this.form.submit()" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </form>
            </div>
        </div>

        @if($groups->isEmpty())
            <div class="empty-state">
                <div class="icon-wrap">
                    @if($hasFilters)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-6 h-6">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-6 h-6">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    @endif
                </div>
                @if($hasFilters)
                    <p class="text-sm font-500 text-gray-700 mb-1">No groups match your filters</p>
                    <p class="text-xs text-gray-400 mb-4">Try a different search term or status.</p>
                    <a href="{{ route('groups.index') }}" class="link-action">Clear filters</a>
                @else
                    <p class="text-sm font-500 text-gray-700 mb-1">No groups yet</p>
                    <p class="text-xs text-gray-400">Add your bot to a Telegram group to get started — it'll show up here
                        automatically.</p>
                @endif
            </div>
        @else
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Telegram ID</th>
                            <th>Status</th>
                            <th>Borrowers</th>
                            <th>Active Loans</th>
                            <th>Joined</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groups as $group)
                            <tr>
                                <td>
                                    <div class="font-600">{{ $group->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $group->settings['currency'] ?? '$' }} ·
                                        {{ $group->settings['reminder_frequency'] ?? 'daily' }} reminders
                                    </div>
                                </td>
                                <td><span class="mono-id">{{ $group->telegram_group_id }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $group->status }}">{{ ucfirst($group->status) }}</span>
                                </td>
                                <td>{{ $group->borrowers_count ?? 0 }}</td>
                                <td>{{ $group->active_loans_count ?? 0 }}</td>
                                <td class="text-xs text-gray-400">{{ $group->joined_at?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <div class="flex justify-end">
                                        <details class="action-menu">
                                            <summary>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    class="w-4 h-4">
                                                    <circle cx="12" cy="5" r="1.5" />
                                                    <circle cx="12" cy="12" r="1.5" />
                                                    <circle cx="12" cy="19" r="1.5" />
                                                </svg>
                                            </summary>
                                            <div class="dropdown">
                                                <a href="{{ route('groups.show', $group) }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        class="w-4 h-4">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                    View details
                                                </a>
                                                @if($group->status === 'pending')
                                                    <form method="POST" action="{{ route('groups.approve', $group) }}">
                                                        @csrf @method('PATCH')
                                                        <button type="submit">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                                class="w-4 h-4">
                                                                <polyline points="20 6 9 17 4 12" />
                                                            </svg>
                                                            Approve
                                                        </button>
                                                    </form>
                                                @endif
                                                @if(in_array($group->status, ['active', 'pending']))
                                                    <hr>
                                                    <form method="POST" action="{{ route('groups.suspend', $group) }}">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="danger">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                                class="w-4 h-4">
                                                                <circle cx="12" cy="12" r="10" />
                                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                                                            </svg>
                                                            Suspend
                                                        </button>
                                                    </form>
                                                @endif
                                                @if($group->status === 'suspended')
                                                    <hr>
                                                    <form method="POST" action="{{ route('groups.unsuspend', $group) }}">
                                                        @csrf @method('PATCH')
                                                        <button type="submit">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                                class="w-4 h-4">
                                                                <polyline points="1 4 1 10 7 10" />
                                                                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
                                                            </svg>
                                                            Unsuspend
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($groups->hasPages())
                <div class="mt-2">{{ $groups->onEachSide(1)->links('vendor.pagination.custom') }}</div>
            @endif
        @endif
    </div>
@endsection
