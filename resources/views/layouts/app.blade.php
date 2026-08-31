<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Loan Bot') — Dashboard</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap"
        rel="stylesheet">

    {{-- Tailwind CDN (replace with compiled in production) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Syne', 'sans-serif'],
                        body: ['DM Sans', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #ecececff;
            color: #111827;
        }

        h1,
        h2,
        h3,
        h4,
        h5 {
            font-family: 'Syne', sans-serif;
        }

        /* Sidebar — full-height, edge-to-edge */
        .sidebar {
            width: 260px;
            background: #ffffff;
            border-right: 1px solid #eef0f3;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
        }

        .sidebar-scroll {
            overflow-y: auto;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 16px;
            border-radius: 8px;
            color: #004E80;
            font-size: 14px;
            font-weight: 500;
            transition: all .15s;
        }

        .nav-link:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .nav-link.active {
            background: #eafcf1;
            color: #004E80;
            font-weight: 600;
        }

        .nav-link svg {
            flex-shrink: 0;
        }

        /* Profile block at bottom of sidebar */
        .profile-block {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
            transition: background .15s;
        }

        .profile-block:hover {
            background: #f7f8fa;
        }

        /* Cards — clean, minimal, consistent */
        .card {
            background: #ffffff;
            border: 1px solid #eef0f3;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #eef0f3;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 16px rgba(16, 24, 40, .04);
            transition: box-shadow .2s, border-color .2s;
        }

        .stat-card:hover {
            box-shadow: 0 6px 24px rgba(16, 24, 40, .08);
            border-color: #e2e5ea;
        }

        /* Solid icon boxes on stat cards */
        .icon-box {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Trend text (icon + colored label, no pill) */
        .trend-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .trend-text svg {
            width: 14px;
            height: 14px;
        }

        .trend-up {
            color: #16a34a;
        }

        .trend-flat {
            color: #9ca3af;
        }

        .trend-bad {
            color: #ef4444;
        }

        .dot {
            width: 7px;
            height: 7px;
            border-radius: 99px;
            display: inline-block;
        }

        /* Progress bar */
        .progress-track {
            width: 100%;
            height: 6px;
            border-radius: 99px;
            background: #eef0f3;
            overflow: hidden;
            margin-top: 14px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 99px;
            background: #3b82f6;
        }

        /* Avatars (solid color circles, color derived per row) */
        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 99px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            font-family: 'Syne', sans-serif;
            color: #fff;
            flex-shrink: 0;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #eafcf1;
            color: #16a34a;
        }

        .badge-overdue {
            background: #fef2f2;
            color: #dc2626;
        }

        .badge-paid {
            background: #eff6ff;
            color: #2563eb;
        }

        .badge-completed {
            background: #ecfdf5;
            color: #0d9488;
        }

        .badge-defaulted {
            background: #fff7ed;
            color: #ea580c;
        }

        .badge-pending {
            background: #fffbeb;
            color: #d97706;
        }

        .badge-cancelled {
            background: #f3f4f6;
            color: #6b7280;
        }

        .badge-suspended {
            background: #f3f4f6;
            color: #6b7280;
        }

        .badge-blacklisted {
            background: #fef2f2;
            color: #dc2626;
        }

        .badge-rejected {
            background: #fef2f2;
            color: #dc2626;
        }

        .badge-approved {
            background: #eafcf1;
            color: #16a34a;
        }

        .badge-sent {
            background: #eafcf1;
            color: #16a34a;
        }

        .badge-failed {
            background: #fef2f2;
            color: #dc2626;
        }

        .badge-success {
            background: #eff6ff;
            color: #1661d3;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
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
        }

        .btn-primary {
            background: #4379EE;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #5238e4ff;
        }

        .btn-ghost {
            background: #ffffff;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .btn-ghost:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .btn-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fee2e2;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }

        /* Link-style action */
        .link-action {
            color: #2563eb;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .link-action:hover {
            text-decoration: underline;
        }

        /* Form inputs */
        .form-input {
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

        .form-input:focus {
            border-color: #22c55e;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #47494eff;
            margin-bottom: 6px;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%236b7280' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            padding-right: 36px;
        }

        /* Pill-style dropdown filter (used in table toolbars, e.g. Groups "All Status") */
        .filter-select {
            appearance: none;
            background-color: #ffffff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2316a34a' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
            border: 1px solid #d1fae5;
            color: #16a34a;
            border-radius: 99px;
            padding: 9px 34px 9px 16px;
            font-size: 14px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            transition: border-color .15s;
        }

        .filter-select:focus {
            border-color: #16a34a;
        }

        /* Small inline table search */
        .table-search-wrap {
            position: relative;
        }

        .table-search-wrap svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: #9ca3af;
        }

        .table-search {
            background: #f7f8fa;
            border: 1px solid #eef0f3;
            border-radius: 10px;
            padding: 9px 14px 9px 38px;
            font-size: 14px;
            color: #111827;
            outline: none;
            width: 220px;
            transition: border-color .15s, background .15s;
        }

        .table-search:focus {
            border-color: #22c55e;
            background: #ffffff;
        }

        /* Monospace muted id chip (e.g. Telegram ID) */
        .mono-id {
            font-family: 'DM Sans', monospace;
            font-size: 13px;
            color: #6b7280;
            background: #f7f8fa;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Row action kebab menu (no JS framework required — uses <details>) */
        .action-menu {
            position: relative;
        }

        tr:has(.action-menu[open]) td {
            padding-bottom: 120px;
        }

        .action-menu[open] {
            z-index: 40;
        }

        .action-menu summary {
            list-style: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .action-menu summary::-webkit-details-marker {
            display: none;
        }

        .action-menu summary:hover {
            background: #f3f4f6;
        }

        .action-menu[open] summary {
            background: #f3f4f6;
            border-color: #e5e7eb;
        }

        .action-menu .dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: #ffffff;
            border: 1px solid #eef0f3;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(16, 24, 40, .10);
            min-width: 160px;
            padding: 6px;
            z-index: 30;
        }

        .action-menu .dropdown a,
        .action-menu .dropdown button {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            text-align: left;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 14px;
            color: #374151;
            background: none;
            border: none;
            cursor: pointer;
        }

        .action-menu .dropdown a:hover,
        .action-menu .dropdown button:hover {
            background: #f7f8fa;
        }

        .action-menu .dropdown .danger {
            color: #dc2626;
        }

        .action-menu .dropdown .danger:hover {
            background: #fef2f2;
        }

        .action-menu .dropdown hr {
            border: none;
            border-top: 1px solid #f0f1f3;
            margin: 6px 0;
        }

        /* Table toolbar row */
        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        /* Header — floats directly on page background, pill search + circular icon buttons */
        .search-wrap {
            position: relative;
        }

        .search-wrap svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 17px;
            height: 17px;
            color: #9ca3af;
        }

        .search-input {
            width: 340px;
            background: #ffffff;
            border: 1px solid #e9ebee;
            border-radius: 99px;
            padding: 12px 18px 12px 44px;
            color: #111827;
            font-size: 14px;
            outline: none;
            box-shadow: 0 2px 10px rgba(16, 24, 40, .04);
            transition: border-color .15s;
        }

        .search-input:focus {
            border-color: #22c55e;
        }

        .search-input::placeholder {
            color: #c1c5cb;
        }

        .icon-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 99px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #374151;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(16, 24, 40, .04);
            flex-shrink: 0;
        }

        .icon-btn:hover {
            background: #f9fafb;
        }

        .notif-dot {
            position: absolute;
            top: 9px;
            right: 10px;
            width: 7px;
            height: 7px;
            border-radius: 99px;
            background: #ef4444;
            border: 1.5px solid #ffffff;
        }

        /* Table */
        .table-container,
        .table-container1,
        .table-container2 {
            width: 100%;
            overflow-x: auto;
        }

        .data-table,
        .data-table1,
        .data-table2 {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table1 th,
        .data-table2 th {
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #9ca3af;
            text-transform: none;
            letter-spacing: 0;
            border-bottom: 1px solid #f0f1f3;
            white-space: nowrap;
        }

        .data-table td,
        .data-table1 td,
        .data-table2 td {
            padding: 16px;
            font-size: 14px;
            color: #111827;
            border-bottom: 1px solid #f5f6f8;
            vertical-align: middle;
        }

        .data-table tr:hover td,
        .data-table1 tr:hover td,
        .data-table2 tr:hover td {
            background: #fafbfc;
        }

        .data-table tr:last-child td,
        .data-table1 tr:last-child td,
        .data-table2 tr:last-child td {
            border-bottom: none;
        }

        /* Pagination */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 20px;
            font-size: 14px;
            color: #9ca3af;
        }

        .page-pill {
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border: none;
            background: transparent;
            text-decoration: none;
        }

        .page-pill.active {
            background: #f3f4f6;
            color: #111827;
        }

        .page-pill:hover:not(.active) {
            background: #f9fafb;
        }

        .page-pill:disabled,
        .page-pill.disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .page-arrow {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            cursor: pointer;
            text-decoration: none;
        }

        .page-arrow:hover {
            background: #f9fafb;
        }

        .page-arrow.disabled {
            opacity: .35;
            pointer-events: none;
        }

        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 32px 12px;
        }

        .empty-state .icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            color: #9ca3af;
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #eafcf1;
            border: 1px solid #86efac;
            color: #16a34a;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
        }

        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #d97706;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 99px;
        }
    </style>

    @stack('styles')
</head>

<body class="h-full">

    {{-- Sidebar --}}
    <aside class="sidebar flex flex-col z-50">
        {{-- Logo --}}
        <div class="px-5 pt-6 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#3b82f6;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" class="w-4.5 h-4.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5z" />
                        <path d="M2 17l10 5 10-5" />
                        <path d="M2 12l10 5 10-5" />
                    </svg>
                </div>
                <div>
                    <div class="font-display font-700 text-md text-gray-900">LoanBot</div>
                    <div class="text-sm text-gr ay-400">Admin Panel</div>
                </div>
            </div>
        </div>

        <div class="mx-5 border-t border-gray-100 mb-3"></div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 pb-4 sidebar-scroll space-y-1">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                @include('icons.Dashboard.Dashboard', ['width' => 25, 'height' => 25, 'class' => 'text-gray-500'])
                <span class="text-md">Overview</span>
            </a>
            <a href="{{ route('groups.index') }}" class="nav-link {{ request()->routeIs('groups.*') ? 'active' : '' }}">
                @include('icons.Group.Group', ['width' => 25, 'height' => 25, 'class' => 'text-gray-500'])
                <span class="text-md">Groups</span>
            </a>
            <a href="{{ route('borrowers.index') }}"
                class="nav-link {{ request()->routeIs('borrowers.*') ? 'active' : '' }}">
                @include('icons.User.Users', ['width' => 25, 'height' => 25, 'class' => 'text-gray-500'])
                <span class="text-md">Borrowers</span>
            </a>
            <a href="{{ route('loans.index') }}" class="nav-link {{ request()->routeIs('loans.*') ? 'active' : '' }}">
                @include('icons.Loan.Loan', ['width' => 30, 'height' => 30, 'class' => 'text-gray-500'])
                <span class="text-md">Loans</span>
            </a>
            <a href="{{ route('payments.index') }}"
                class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                @include('icons.Payment.Payment', ['width' => 30, 'height' => 30, 'class' => 'text-gray-500'])
                <span class="text-md">Payments</span>
            </a>
            <a href="{{ route('reminders.index') }}"
                class="nav-link {{ request()->routeIs('reminders.index') ? 'active' : '' }}">
                @include('icons.Reminder.Reminder', ['width' => 25, 'height' => 25, 'class' => 'text-gray-500'])
                <span class="text-md">Reminders</span>
            </a>
            <a href="{{ route('reminders.settings') }}"
                class="nav-link {{ request()->routeIs('reminders.settings') ? 'active' : '' }}">
                @include('icons.Setting.Setting', ['class' => 'w-6 h-6 text-gray-500'])
                <span class="text-md">Reminder Settings</span>
            </a>

            <div class="text-xs font-600 text-gray-300 px-3 pb-1 pt-4 uppercase tracking-widest">Bot</div>
            <a href="{{ route('bot.setup') }}" class="nav-link {{ request()->routeIs('bot.*') ? 'active' : '' }}">
                @include('icons.Bot.Bot', ['width' => 30, 'height' => 30, 'class' => 'text-gray-500'])
                <span class="text-md">Bot Setup</span>
            </a>
        </nav>

        {{-- Profile block — pinned to bottom --}}
        <div class="px-3 pb-4 pt-2 border-t border-gray-100">
            <a href="{{ route('logout') }}"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                class="profile-block">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-700 font-display flex-shrink-0"
                    style="background:#e5e7eb;color:#4b5563;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-600 truncate text-gray-900">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-gray-400">{{ auth()->user()->tenant?->name ?? 'Profile' }}</div>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    class="w-4 h-4 text-gray-300 flex-shrink-0">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
        </div>
    </aside>

    {{-- Main content --}}
    <div class="flex flex-col" style="margin-left:260px; min-height:100vh;">
        {{-- Top bar --}}
        <header class="px-8 pt-6 pb-2 flex items-center justify-between">
            <form action="{{ route('loans.index') }}" method="GET" class="search-wrap hidden md:block">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" name="search" class="search-input" placeholder="Search...">
            </form>
            <div class="flex items-center gap-3">
                @stack('header-actions')
                <a href="{{ route('reminders.index') }}" class="icon-btn">
                    @include('icons.Notification.Notification', [
                        'width' => 28,
                        'height' => 28,
                        'class' => 'text-gray-500'
                    ])
                    @if(($failedReminders ?? 0) > 0)
                        <span class="notif-dot"></span>
                    @endif
                </a>
                <div class="icon-btn" style="background:#D9DDDC;">
                    @include('icons.Profile.Profile', ['width' => 32, 'height' => 32, 'class' => 'text-gray-500'])
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 px-8 pb-8">
            <h1 class="font-family font-bold text-3xl text-gray-700">@yield('page-title', 'Dashboard')</h1>
            <p class="mb-6 text-gray-600">@yield('page-subtitle', 'Go to View and Manage your system here.')</p>
            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success mb-6">✓ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error mb-6">✗ {{ session('error') }}</div>
            @endif
            {{-- Validation errors --}}
            @if($errors->any())
                <div class="alert alert-error mb-6">
                    <div class="font-600 mb-1">Please fix the following errors:</div>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('scripts')
</body>

</html>
