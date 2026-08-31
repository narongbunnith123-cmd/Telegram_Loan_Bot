
@extends('layouts.app')
@section('title', 'Reminder Settings')
@section('page-title', 'Reminder Settings')
@section('page-subtitle', 'Configure reminder schedules and templates.')

@push('header-actions')
    <a href="{{ route('reminders.index') }}" class="btn btn-ghost btn-sm">← Reminder Log</a>
@endpush

@section('content')
<div class="space-y-6">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="p-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- 📤 Manual Reminder Send                        --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display font-700 text-base">📤 Send Manual Reminder</h3>
            <span class="text-xs text-gray-500">Send a reminder to any borrower anytime</span>
        </div>

        <form method="POST" action="{{ route('reminders.manual-send') }}" id="manual-send-form">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                {{-- Group --}}
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Group</label>
                    <select name="group_id" id="manual-group" class="form-input form-select text-sm" required>
                        <option value="">Select group...</option>
                        @foreach($groups as $group)
                        <option value="{{ $group->id }}" data-chat-id="{{ $group->telegram_group_id }}">
                            {{ $group->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Borrower --}}
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Borrower</label>
                    <select name="borrower_id" id="manual-borrower" class="form-input form-select text-sm" required>
                        <option value="">Select group first...</option>
                    </select>
                </div>

                {{-- Template --}}
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Template</label>
                    <select name="template_id" class="form-input form-select text-sm" required>
                        <optgroup label="📋 Rule-Based Templates">
                        @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                        </optgroup>
                        <optgroup label="📌 Daily Interest Templates">
                        @foreach($interestTemplates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                {{-- Send Type --}}
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Send Type</label>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="send_type" value="now" checked class="accent-green-500">
                            <span class="text-sm">🚀 Send Now</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="send_type" value="scheduled" class="accent-blue-500">
                            <span class="text-sm">⏰ Schedule</span>
                        </label>
                    </div>
                </div>

                {{-- Schedule Date/Time (hidden by default) --}}
                <div id="schedule-fields" class="hidden">
                    <label class="text-xs text-gray-400 mb-1 block">Date</label>
                    <input type="date" name="scheduled_date" class="form-input text-sm"
                           value="{{ now()->addDay()->format('Y-m-d') }}">
                </div>
                <div id="schedule-time-field" class="hidden">
                    <label class="text-xs text-gray-400 mb-1 block">Time</label>
                    <input type="time" name="scheduled_time" class="form-input text-sm" value="08:00">
                </div>

                {{-- Mention --}}
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Mention Borrower</label>
                    <label class="flex items-center gap-2 cursor-pointer mt-2">
                        <input type="checkbox" name="mention" value="1" checked class="accent-green-500 w-4 h-4">
                        <span class="text-sm text-gray-300">@mention in message</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm" id="manual-send-btn">
                🚀 Send Reminder
            </button>
        </form>
    </div>
    {{-- ═══════════════════════════════════════════════ --}}
    {{-- 📌 Daily Interest Message Templates             --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display font-700 text-base">📌 Daily Interest Message Templates</h3>
            <span class="text-xs text-gray-500">These templates are used by the automated daily interest reminders</span>
        </div>

        <p class="text-sm text-gray-400 mb-4">
            Edit these templates to customize the messages sent to Telegram groups.
            Use <strong>{placeholders}</strong> and they will be replaced with real loan data.
        </p>

        {{-- Available Placeholders --}}
        <div class="mb-5 flex flex-wrap gap-1.5">
            @foreach($placeholders as $ph => $desc)
            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-800 border border-gray-700 text-gray-300 cursor-help" title="{{ $desc }}">{{ $ph }}</span>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($interestTemplates as $tmpl)
            <div class="p-4 rounded-xl border border-gray-800 bg-gray-900/50">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="inline-block w-2 h-2 rounded-full mr-1.5
                            {{ $tmpl->reminder_type === 'interest_normal' ? 'bg-green-400' :
                               ($tmpl->reminder_type === 'interest_warning' ? 'bg-yellow-400' :
                               ($tmpl->reminder_type === 'interest_escalation' ? 'bg-red-400' : 'bg-blue-400')) }}"></span>
                        <span class="font-600 text-sm">{{ $tmpl->name }}</span>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full
                        {{ $tmpl->reminder_type === 'interest_normal' ? 'text-green-400 bg-green-400/10' :
                           ($tmpl->reminder_type === 'interest_warning' ? 'text-yellow-400 bg-yellow-400/10' :
                           ($tmpl->reminder_type === 'interest_escalation' ? 'text-red-400 bg-red-400/10' : 'text-blue-400 bg-blue-400/10')) }}">
                        {{ str_replace('interest_', '', $tmpl->reminder_type) }}
                    </span>
                </div>

                <form method="POST" action="{{ route('reminders.update-template', $tmpl) }}">
                    @csrf @method('PATCH')

                    {{-- Tone --}}
                    <div class="mb-3">
                        <label class="text-xs text-gray-400 mb-1 block">Tone</label>
                        <select name="tone" class="form-input form-select text-sm">
                            @foreach(['gentle' => '😊 Gentle', 'balanced' => '⚖️ Balanced', 'aggressive' => '🚨 Aggressive'] as $val => $label)
                            <option value="{{ $val }}" {{ $tmpl->tone === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Message Template --}}
                    <div class="mb-3">
                        <label class="text-xs text-gray-400 mb-1 block">Message Template</label>
                        <textarea name="message_template" rows="6"
                            class="form-input text-sm font-mono w-full"
                            style="white-space: pre-wrap;">{{ $tmpl->message_template }}</textarea>
                    </div>

                    <input type="hidden" name="name" value="{{ $tmpl->name }}">

                    {{-- Preview Area --}}
                    <div id="interest-preview-{{ $tmpl->id }}" class="mb-3 hidden">
                        <label class="text-xs text-gray-400 mb-1 block">Preview</label>
                        <div class="p-3 rounded-lg bg-gray-800/50 border border-gray-700 text-sm interest-preview-content"
                             style="white-space: pre-wrap;"></div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" {{ $tmpl->enabled ? 'checked' : '' }} class="accent-green-500">
                            <span class="text-xs text-gray-400">Enabled</span>
                        </label>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-ghost btn-sm text-xs interest-preview-btn"
                                data-template-id="{{ $tmpl->id }}"
                                data-url="{{ route('reminders.preview-template', $tmpl) }}">
                                👁 Preview
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm text-xs text-yellow-400 interest-test-btn"
                                data-template-id="{{ $tmpl->id }}"
                                data-url="{{ route('reminders.test-send', $tmpl) }}">
                                🚀 Test Send
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">💾 Save</button>
                        </div>
                    </div>
                </form>
            </div>
            @endforeach
        </div>

        @if($interestTemplates->isEmpty())
        <div class="text-center text-gray-500 py-6">
            <p class="text-sm">No daily interest templates found. Run migration to create them.</p>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- 🔔 Daily Interest Reminder Settings            --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display font-700 text-base">🔔 Daily Interest Reminder Settings</h3>
            <span class="text-xs text-gray-500">Configure when daily interest reminders are sent per group</span>
        </div>

        <p class="text-sm text-gray-400 mb-4">
            The system sends <strong>2 reminders per day</strong> for unpaid interest. When a borrower pays, reminders stop for that day.
            Tone <strong>escalates automatically</strong> based on consecutive unpaid days (friendly → warning → urgent).
        </p>

        <div class="space-y-3">
            @foreach($groups as $group)
            <form method="POST" action="{{ route('groups.update', $group) }}" class="flex items-center gap-4 p-3 rounded-xl bg-gray-800/30 border border-gray-800">
                @csrf @method('PATCH')
                {{-- Hidden fields to preserve existing settings --}}
                <input type="hidden" name="currency" value="{{ $group->settings['currency'] ?? '$' }}">
                <input type="hidden" name="interest_rate" value="{{ $group->settings['interest_rate'] ?? '' }}">
                <input type="hidden" name="interest_type" value="{{ $group->settings['interest_type'] ?? 'percentage' }}">
                <input type="hidden" name="reminder_frequency" value="{{ $group->settings['reminder_frequency'] ?? 'daily' }}">
                <input type="hidden" name="reminder_days_before" value="{{ $group->settings['reminder_days_before'] ?? 3 }}">
                <input type="hidden" name="late_penalty" value="{{ $group->settings['late_penalty'] ?? '' }}">

                <div class="flex-1 min-w-0">
                    <div class="font-600 text-sm text-gray-200 truncate">{{ $group->name }}</div>
                    <div class="text-xs text-gray-500">{{ $group->loans()->whereIn('status', ['active','overdue'])->count() }} active loans</div>
                </div>

                <div class="flex items-center gap-3">
                    <div>
                        <label class="text-[10px] text-gray-500 block mb-1">1st Reminder</label>
                        <input type="time" name="reminder_time_1"
                               value="{{ $group->reminder_time_1 ?? '17:00' }}"
                               class="form-input text-sm px-2 py-1" style="width:110px;">
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-500 block mb-1">2nd Reminder</label>
                        <input type="time" name="reminder_time_2"
                               value="{{ $group->reminder_time_2 ?? '21:00' }}"
                               class="form-input text-sm px-2 py-1" style="width:110px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-3">Save</button>
                </div>
            </form>
            @endforeach
        </div>

        <div class="mt-4 p-3 rounded-lg bg-gray-800/50 border border-gray-700">
            <div class="text-xs text-gray-400 space-y-1">
                <div class="flex items-center gap-2">📌 <span><strong>Normal</strong> (1-3 unpaid days) — Friendly reminder tone</span></div>
                <div class="flex items-center gap-2">⚠️ <span><strong>Warning</strong> (4-7 unpaid days) — Serious tone, mentions unpaid count</span></div>
                <div class="flex items-center gap-2">🚨 <span><strong>Escalation</strong> (7+ unpaid days) — Urgent, demands immediate payment</span></div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- 🎯 Reminder Strategy                           --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display font-700 text-base">🎯 Reminder Strategy</h3>
            <span class="text-xs text-gray-500">Apply a preset to all rules at once</span>
        </div>

        <form method="POST" action="{{ route('reminders.apply-strategy') }}" onsubmit="return confirm('This will update all reminder rules. Continue?')">
            @csrf
            <div class="flex flex-wrap gap-3 mb-4">
                @foreach(['gentle' => ['😊', 'Low frequency group reminders'], 'balanced' => ['⚖️', 'Adaptive group reminders, moderate frequency'], 'aggressive' => ['🚨', 'Daily group reminders, fast escalation']] as $strategyKey => [$icon, $desc])
                <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-700 cursor-pointer hover:border-gray-500 transition-colors has-[:checked]:border-green-500 has-[:checked]:bg-green-500/5 flex-1 min-w-[200px]">
                    <input type="radio" name="strategy" value="{{ $strategyKey }}" class="accent-green-500"
                           {{ $strategyKey === 'balanced' ? 'checked' : '' }}>
                    <div>
                        <div class="font-600 text-sm">{{ $icon }} {{ ucfirst($strategyKey) }}</div>
                        <div class="text-xs text-gray-500">{{ $desc }}</div>
                    </div>
                </label>
                @endforeach
            </div>

            <div class="overflow-x-auto mb-4">
                <table class="data-table text-xs">
                    <thead>
                        <tr><th>Stage</th><th>Gentle</th><th>Balanced</th><th>Aggressive</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Before Due</td><td>Once, Group</td><td>Once, Group</td><td>Daily, Group</td></tr>
                        <tr><td>Due Today</td><td>Once, Group</td><td>Once, Group</td><td>Once, Group</td></tr>
                        <tr><td>1-7 Overdue</td><td>Every 2d, Group</td><td>Daily, Group</td><td>Daily, Group</td></tr>
                        <tr><td>14+ Overdue</td><td>Weekly, Group</td><td>Weekly, Group</td><td>Daily, Group</td></tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply Strategy</button>
        </form>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- ⏱️ Reminder Rules                              --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-display font-700 text-base">⏱️ Reminder Rules</h3>
            <span class="text-xs text-gray-500">Controls when and how often group reminders are sent</span>
        </div>

        {{-- Visual Timeline --}}
        <div class="mb-6 overflow-x-auto">
            <div class="flex items-center gap-0 min-w-[600px] px-4">
                @foreach($rules->sortBy('days_offset') as $rule)
                <div class="flex flex-col items-center flex-1">
                    <div class="text-xs font-mono mb-1 {{ $rule->days_offset < 0 ? 'text-blue-400' : ($rule->days_offset === 0 ? 'text-green-400' : 'text-red-400') }}">
                        @if($rule->days_offset < 0) {{ abs($rule->days_offset) }}d before
                        @elseif($rule->days_offset === 0) Due Day
                        @else +{{ $rule->days_offset }}d
                        @endif
                    </div>
                    <div class="w-4 h-4 rounded-full border-2 {{ $rule->enabled ? ($rule->days_offset < 0 ? 'border-blue-400 bg-blue-400/20' : ($rule->days_offset === 0 ? 'border-green-400 bg-green-400/20' : 'border-red-400 bg-red-400/20')) : 'border-gray-700 bg-gray-800' }}"></div>
                    <div class="text-[10px] text-gray-500 mt-1">{{ $rule->send_time ?? '08:00' }}</div>
                </div>
                @if(!$loop->last)
                <div class="w-full h-px bg-gray-700 flex-1 mt-3"></div>
                @endif
                @endforeach
            </div>
        </div>

        {{-- Rules Table --}}
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Rule</th>
                        <th>Trigger</th>
                        <th>Frequency</th>
                        <th>Template</th>
                        <th>Send Time</th>
                        <th>Cooldown</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules->sortBy('days_offset') as $rule)
                    <tr id="rule-row-{{ $rule->id }}">
                        <form method="POST" action="{{ route('reminders.update-rule', $rule) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="send_to_group" value="1">
                            <td>
                                <input type="text" name="name" value="{{ $rule->name }}"
                                       class="form-input py-1 px-2 text-sm w-40">
                            </td>
                            <td class="font-mono text-xs {{ $rule->days_offset < 0 ? 'text-blue-400' : ($rule->days_offset === 0 ? 'text-green-400' : 'text-red-400') }}">
                                @if($rule->days_offset < 0) {{ $rule->days_offset }}d
                                @elseif($rule->days_offset === 0) Due Day
                                @else +{{ $rule->days_offset }}d
                                @endif
                            </td>
                            <td>
                                <select name="frequency_type" class="form-input form-select py-1 px-2 text-xs w-28">
                                    @foreach(['once', 'daily', 'every_2_days', 'weekly'] as $freq)
                                    <option value="{{ $freq }}" {{ $rule->frequency_type === $freq ? 'selected' : '' }}>
                                        {{ str_replace('_', ' ', ucfirst($freq)) }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-xs text-gray-400">
                                @if($rule->template)
                                    <span class="text-green-400">{{ $rule->template->name }}</span>
                                @else
                                    <span class="text-yellow-500">Fallback</span>
                                @endif
                            </td>
                            <td>
                                <input type="time" name="send_time" value="{{ $rule->send_time ?? '08:00' }}"
                                       class="form-input py-1 px-2 text-xs w-24">
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <input type="number" name="cooldown_hours" value="{{ $rule->cooldown_hours }}"
                                           class="form-input py-1 px-2 text-xs w-14" min="1" max="720">
                                    <span class="text-xs text-gray-500">hrs</span>
                                </div>
                            </td>
                            <td>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="enabled" value="1" {{ $rule->enabled ? 'checked' : '' }}
                                           class="accent-green-500 w-4 h-4">
                                    <span class="text-xs {{ $rule->enabled ? 'text-green-400' : 'text-gray-500' }}">
                                        {{ $rule->enabled ? 'On' : 'Off' }}
                                    </span>
                                </label>
                            </td>
                            <td>
                                <button type="submit" class="btn btn-primary btn-sm text-xs py-1 px-3">Save</button>
                            </td>
                        </form>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- 📝 Message Templates                           --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-display font-700 text-base">📝 Message Templates</h3>
            <span class="text-xs text-gray-500">Customize reminder messages with placeholders</span>
        </div>

        <div class="mb-5 p-3 rounded-lg bg-gray-800/50 border border-gray-700">
            <div class="text-xs font-700 text-gray-400 uppercase tracking-wider mb-2">Available Placeholders</div>
            <div class="flex flex-wrap gap-2">
                @foreach($placeholders as $placeholder => $description)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-700/50 border border-gray-600 text-xs cursor-help"
                      title="{{ $description }}">
                    <code class="text-green-400">{{ $placeholder }}</code>
                </span>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($templates as $template)
            <div class="rounded-xl border border-gray-700 bg-gray-800/30 overflow-hidden">
                <form method="POST" action="{{ route('reminders.update-template', $template) }}">
                    @csrf @method('PATCH')

                    <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $template->enabled ? 'bg-green-400' : 'bg-gray-600' }}"></span>
                            <input type="text" name="name" value="{{ $template->name }}"
                                   class="bg-transparent border-0 font-600 text-sm text-white focus:outline-none w-48">
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="badge badge-{{ $template->reminder_type === 'overdue' || $template->reminder_type === 'escalation' ? 'overdue' : ($template->reminder_type === 'due_today' ? 'active' : 'pending') }}">
                                {{ str_replace('_', ' ', ucfirst($template->reminder_type)) }}
                            </span>
                            <span class="text-green-400 font-600">→ GROUP</span>
                        </div>
                    </div>

                    <div class="p-4 space-y-3">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Tone</label>
                            <select name="tone" class="form-input form-select py-1.5 text-xs">
                                @foreach(['gentle', 'balanced', 'aggressive'] as $tone)
                                <option value="{{ $tone }}" {{ $template->tone === $tone ? 'selected' : '' }}>
                                    {{ $tone === 'gentle' ? '😊 Gentle' : ($tone === 'balanced' ? '⚖️ Balanced' : '🚨 Aggressive') }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Message Template</label>
                            <textarea name="message_template" rows="6"
                                      class="form-input text-xs font-mono leading-relaxed template-textarea"
                                      data-template-id="{{ $template->id }}">{{ $template->message_template }}</textarea>
                        </div>

                        <div class="preview-area hidden" id="preview-{{ $template->id }}">
                            <label class="text-xs text-gray-400 mb-1 block">Preview</label>
                            <div class="p-3 rounded-lg bg-gray-900 border border-gray-700 text-xs whitespace-pre-wrap text-gray-300"
                                 id="preview-text-{{ $template->id }}"></div>
                        </div>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-700 flex items-center justify-between bg-gray-800/30">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" {{ $template->enabled ? 'checked' : '' }}
                                   class="accent-green-500 w-4 h-4">
                            <span class="text-xs text-gray-400">Enabled</span>
                        </label>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-ghost btn-sm text-xs preview-btn"
                                    data-template-id="{{ $template->id }}"
                                    data-preview-url="{{ route('reminders.preview-template', $template) }}">
                                👁 Preview
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm text-xs test-send-btn"
                                    data-template-id="{{ $template->id }}"
                                    data-test-url="{{ route('reminders.test-send', $template) }}">
                                🚀 Test Send
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm text-xs">
                                {{ $template->tenant_id === null ? '📋 Clone & Save' : '💾 Save' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endforeach
        </div>
    </div>

</div>

@push('scripts')
<script>
    // ── Manual Send: Toggle schedule fields ──────────
    document.querySelectorAll('input[name="send_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const scheduleFields = document.getElementById('schedule-fields');
            const scheduleTime = document.getElementById('schedule-time-field');
            const sendBtn = document.getElementById('manual-send-btn');

            if (this.value === 'scheduled') {
                scheduleFields.classList.remove('hidden');
                scheduleTime.classList.remove('hidden');
                sendBtn.textContent = '⏰ Schedule Reminder';
            } else {
                scheduleFields.classList.add('hidden');
                scheduleTime.classList.add('hidden');
                sendBtn.textContent = '🚀 Send Reminder';
            }
        });
    });

    // ── Manual Send: Load borrowers when group changes ──
    document.getElementById('manual-group').addEventListener('change', async function() {
        const borrowerSelect = document.getElementById('manual-borrower');
        borrowerSelect.innerHTML = '<option value="">Loading...</option>';

        if (!this.value) {
            borrowerSelect.innerHTML = '<option value="">Select group first...</option>';
            return;
        }

        try {
            const response = await fetch(`{{ route('reminders.borrowers') }}?group_id=${this.value}`);
            const borrowers = await response.json();

            if (borrowers.length === 0) {
                borrowerSelect.innerHTML = '<option value="">No borrowers with active loans</option>';
                return;
            }

            borrowerSelect.innerHTML = '<option value="">Select borrower...</option>';
            borrowers.forEach(b => {
                const username = b.telegram_username ? ` (@${b.telegram_username})` : '';
                borrowerSelect.innerHTML += `<option value="${b.id}">${b.name}${username}</option>`;
            });
        } catch (e) {
            borrowerSelect.innerHTML = '<option value="">Error loading borrowers</option>';
        }
    });

    // ── Template Preview ─────────────────────────────
    document.querySelectorAll('.preview-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const templateId = this.dataset.templateId;
            const previewUrl = this.dataset.previewUrl;
            const textarea = document.querySelector(`textarea[data-template-id="${templateId}"]`);
            const previewArea = document.getElementById(`preview-${templateId}`);
            const previewText = document.getElementById(`preview-text-${templateId}`);

            previewText.textContent = 'Loading...';
            previewArea.classList.remove('hidden');

            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message_template: textarea.value }),
                });
                const data = await response.json();
                previewText.textContent = data.preview;
            } catch (e) {
                previewText.textContent = '(Preview failed — ' + e.message + ')';
            }
        });
    });

    // ── Test Send ────────────────────────────────────
    document.querySelectorAll('.test-send-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Send this template as a test message to your Telegram group?')) return;

            const originalText = this.textContent;
            this.textContent = '⏳ Sending...';
            this.disabled = true;

            try {
                const response = await fetch(this.dataset.testUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message_template: document.querySelector(`textarea[data-template-id="${this.dataset.templateId}"]`).value
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    this.textContent = '✅ Sent!';
                } else {
                    this.textContent = '❌ Failed';
                    alert('Send failed: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                this.textContent = '❌ Error';
                alert('Error: ' + e.message);
            }
            setTimeout(() => { this.textContent = originalText; this.disabled = false; }, 3000);
        });
    });

    // ── Interest Template Preview ────────────────────
    document.querySelectorAll('.interest-preview-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const templateId = this.dataset.templateId;
            const url = this.dataset.url;
            const card = this.closest('.p-4');
            const textarea = card.querySelector('textarea[name="message_template"]');
            const previewDiv = document.getElementById('interest-preview-' + templateId);
            const previewContent = previewDiv.querySelector('.interest-preview-content');

            this.textContent = '⏳ Loading...';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message_template: textarea.value }),
                });
                const data = await response.json();
                previewContent.innerHTML = data.preview;
                previewDiv.classList.remove('hidden');
            } catch (e) {
                previewContent.textContent = '(Preview failed — ' + e.message + ')';
                previewDiv.classList.remove('hidden');
            }
            this.textContent = '👁 Preview';
        });
    });

    // ── Interest Template Test Send ──────────────────
    document.querySelectorAll('.interest-test-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Send this template as a test message to your Telegram group?')) return;

            const url = this.dataset.url;
            const card = this.closest('.p-4');
            const textarea = card.querySelector('textarea[name="message_template"]');
            const originalText = this.textContent;
            this.textContent = '⏳ Sending...';
            this.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message_template: textarea.value }),
                });
                const data = await response.json();
                if (data.success) {
                    this.textContent = '✅ Sent!';
                } else {
                    this.textContent = '❌ Failed';
                    alert('Send failed: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                this.textContent = '❌ Error';
                alert('Error: ' + e.message);
            }
            setTimeout(() => { this.textContent = originalText; this.disabled = false; }, 3000);
        });
    });

    // Local-dev fallback: process due scheduled reminders while this page is open.
    async function processDueScheduledReminders() {
        try {
            const response = await fetch(`{{ route('reminders.process-due') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: '{}',
            });

            const data = await response.json();
            if ((data.sent || data.failed) && !document.hidden) {
                window.location.reload();
            }
        } catch (e) {
            // The real scheduler/queue worker is still the primary delivery path.
        }
    }

    processDueScheduledReminders();
    setInterval(processDueScheduledReminders, 10000);
</script>
@endpush
@endsection
