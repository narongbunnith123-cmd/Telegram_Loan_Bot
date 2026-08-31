<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TelegramGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = TelegramGroup::query()
            ->withCount([
                'loans as active_loans_count' => function ($q) {
                    $q->whereIn('status', ['active', 'overdue']);
                }
            ])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        // Attach borrowers_count manually (borrowers are now linked via loans)
        $groups->each(function ($group) {
            $group->borrowers_count = $group->loans()->distinct('borrower_id')->count('borrower_id');
        });

        return view('groups.index', compact('groups'));
    }

    public function show(TelegramGroup $group)
    {
        $group->load(['loans.borrower']);
        $borrowers = $group->borrowers()->get();
        return view('groups.show', compact('group', 'borrowers'));
    }

    public function update(Request $request, TelegramGroup $group)
    {
        $settings = $group->settings ?? [];
        $settings['currency'] = $request->input('currency', '$');
        $settings['interest_rate'] = $request->input('interest_rate');
        $settings['interest_type'] = $request->input('interest_type');
        $settings['reminder_frequency'] = $request->input('reminder_frequency');
        $settings['reminder_time'] = $request->input('reminder_time', '09:00');
        $settings['reminder_days_before'] = (int) $request->input('reminder_days_before', 3);
        $settings['late_penalty'] = $request->input('late_penalty');

        $group->update([
            'settings' => $settings,
            'reminder_time_1' => $request->input('reminder_time_1', '17:00'),
            'reminder_time_2' => $request->input('reminder_time_2', '21:00'),
        ]);

        return back()->with('success', 'Group settings updated.');
    }

    public function approve(TelegramGroup $group)
    {
        $group->update(['status' => 'active']);
        return back()->with('success', "Group '{$group->name}' approved.");
    }

    public function suspend(TelegramGroup $group)
    {
        $group->update(['status' => 'suspended']);
        return back()->with('success', "Group '{$group->name}' suspended.");
    }

    public function unsuspend(TelegramGroup $group)
    {
        $group->update(['status' => 'active']);
        return back()->with('success', "Group '{$group->name}' has been reactivated.");
    }
}
