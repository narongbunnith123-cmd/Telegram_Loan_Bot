{{-- resources/views/bot/setup.blade.php --}}
@extends('layouts.app')
@section('title', 'Bot Setup')
@section('page-title', 'Bot Setup')
@section('page-subtitle', 'Configure your Telegram bot connection.')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    {{-- Current bot status --}}
    @if($botToken)
    <div class="card" style="border-color:#2ea04344;">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#22c55e18;border:1px solid #22c55e44;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" class="w-6 h-6"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="font-display font-700">Bot Connected</div>
                <div class="text-sm text-gray-400">@{{ $botToken->bot_username }} · {{ $botToken->bot_name }}</div>
            </div>
            <div class="ml-auto">
                <span class="badge badge-active">Active</span>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-800">
            <div class="text-xs text-gray-500 mb-2">Webhook URL</div>
            <code class="text-xs text-green-400 break-all">{{ $botToken->webhook_url }}</code>
        </div>

        <div class="flex gap-3 mt-4">
            <form method="POST" action="{{ route('bot.reregister') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">↺ Re-register Webhook</button>
            </form>
            <form method="POST" action="{{ route('bot.disconnect') }}" onsubmit="return confirm('Disconnect bot?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">Disconnect Bot</button>
            </form>
        </div>
    </div>
    @endif

    {{-- Telegram Account Linking --}}
    <div class="card">
        <h2 class="font-display font-700 mb-1">🔗 Link Telegram Account</h2>
        <p class="text-sm text-gray-500 mb-5">
            Connect your Telegram account to use admin commands directly in Telegram groups.
        </p>

        @if(auth()->user()->telegram_user_id)
            {{-- Already linked --}}
            <div class="p-4 rounded-lg mb-4" style="background:#1c2a1c;border:1px solid #2ea04344;">
                <div class="flex items-center gap-3">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" class="w-5 h-5 flex-shrink-0"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <div class="text-sm font-600 text-green-400">Telegram Account Linked</div>
                        <div class="text-xs text-gray-400">Telegram ID: {{ auth()->user()->telegram_user_id }}</div>
                    </div>
                </div>
            </div>
            <div class="text-sm text-gray-400 mb-4">
                You can now use admin commands like <code class="text-green-400">/newloan</code>, <code class="text-green-400">/approve</code>, <code class="text-green-400">/groupstats</code> directly in your Telegram group.
            </div>
            <form method="POST" action="{{ route('bot.unlink') }}" onsubmit="return confirm('Unlink your Telegram account?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">Unlink Telegram</button>
            </form>
        @else
            {{-- Not linked — show code generation --}}
            @if(session('link_code'))
            <div class="p-4 rounded-lg mb-4" style="background:#2d2200;border:1px solid #9e6a0344;">
                <div class="text-xs text-yellow-400 font-600 uppercase mb-2">Your Link Code</div>
                <div class="text-3xl font-display font-800 text-yellow-400 tracking-widest mb-2">{{ session('link_code') }}</div>
                <div class="text-sm text-gray-400">
                    Go to your Telegram group and type:<br>
                    <code class="text-green-400 text-base">/link {{ session('link_code') }}</code>
                </div>
                <div class="text-xs text-gray-500 mt-2">⏱ This code expires in 10 minutes.</div>
            </div>
            @endif

            <div class="flex items-start gap-4 mb-5">
                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0 mt-0.5">1</div>
                <div>
                    <div class="text-sm font-500">Generate a link code</div>
                    <div class="text-xs text-gray-500">Click the button below to get a one-time verification code.</div>
                </div>
            </div>
            <div class="flex items-start gap-4 mb-5">
                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0 mt-0.5">2</div>
                <div>
                    <div class="text-sm font-500">Open your Telegram group</div>
                    <div class="text-xs text-gray-500">Go to any group where your bot is active.</div>
                </div>
            </div>
            <div class="flex items-start gap-4 mb-5">
                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0 mt-0.5">3</div>
                <div>
                    <div class="text-sm font-500">Type <code class="text-green-400">/link YOUR_CODE</code></div>
                    <div class="text-xs text-gray-500">The bot will confirm the link and you'll have admin access.</div>
                </div>
            </div>

            <form method="POST" action="{{ route('bot.link-code') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    Generate Link Code
                </button>
            </form>
        @endif
    </div>

    {{-- Register / Update --}}
    <div class="card">
        <h2 class="font-display font-700 mb-1">{{ $botToken ? 'Update Bot Token' : 'Connect Your Bot' }}</h2>
        <p class="text-sm text-gray-500 mb-5">
            Create a bot via <a href="https://t.me/BotFather" target="_blank" class="text-green-500 hover:underline">@BotFather</a> on Telegram, then paste the token below.
        </p>

        <form method="POST" action="{{ route('bot.register') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="form-label">Bot Token <span class="text-red-400">*</span></label>
                    <input type="text" name="token" class="form-input font-mono text-sm"
                           placeholder="7123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxx" required>
                    @error('token') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-5">Connect Bot</button>
        </form>
    </div>

    {{-- Instructions --}}
    <div class="card">
        <h2 class="font-display font-700 text-sm mb-4">How to set up</h2>
        <ol class="space-y-3 text-sm text-gray-400">
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">1</span>Message <span class="text-green-400">@BotFather</span> on Telegram</li>
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">2</span>Send <code class="text-green-400">/newbot</code> and follow prompts</li>
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">3</span>Copy the API token and paste it above</li>
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">4</span>Click Connect — the webhook is set automatically</li>
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">5</span>Link your Telegram account above to use admin commands</li>
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">6</span>Add your bot to a Telegram group — it will appear as pending</li>
            <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-700 text-white flex-shrink-0">7</span>Approve the group and start creating loans!</li>
        </ol>
    </div>

    {{-- Telegram Command Reference --}}
    <div class="card">
        <h2 class="font-display font-700 text-sm mb-4">📋 Telegram Command Reference</h2>
        <div class="space-y-1">
            <div class="text-xs font-600 text-gray-500 uppercase tracking-widest mb-2">Borrower Commands</div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/myloan</code><span class="text-gray-400">View loan summary</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/balance</code><span class="text-gray-400">Check current balance</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/pay</code><span class="text-gray-400">Submit payment proof</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/statement</code><span class="text-gray-400">View payment history</span></div>

            <div class="text-xs font-600 text-gray-500 uppercase tracking-widest mb-2 mt-4">Admin Commands</div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/newloan @user 500 2 30</code><span class="text-gray-400">Create loan</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/adduser @user Name</code><span class="text-gray-400">Register borrower</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/approve 123</code><span class="text-gray-400">Approve payment</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/reject 123 reason</code><span class="text-gray-400">Reject payment</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/loans</code><span class="text-gray-400">List active loans</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/overdue</code><span class="text-gray-400">View overdue loans</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/cancelloan 45</code><span class="text-gray-400">Cancel loan (with confirm)</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/groupstats</code><span class="text-gray-400">Group summary stats</span></div>
            <div class="flex justify-between text-sm py-1.5 border-b border-gray-800"><code class="text-green-400">/import</code><span class="text-gray-400">Import borrower via forward</span></div>
            <div class="flex justify-between text-sm py-1.5"><code class="text-green-400">/settings</code><span class="text-gray-400">View & edit group settings</span></div>
        </div>
    </div>

</div>
@endsection
