<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\GroupParticipant;
use App\Models\TelegramGroup;
use App\Services\BorrowerService;
use Illuminate\Http\Request;

class BorrowerController extends Controller
{
    public function __construct(
        private BorrowerService $borrowerService,
    ) {
    }

    public function index(Request $request)
    {
        $groups = TelegramGroup::where('status', 'active')->get();

        $borrowers = Borrower::with(['loans.group'])
            ->when($request->search, fn($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('name', 'like', "%{$s}%")
                    ->orWhere('telegram_username', 'like', "%{$s}%")
                    ->orWhere('phone_number', 'like', "%{$s}%")
                    ->orWhere('borrower_code', 'like', "%{$s}%");
            }))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->verification, fn($q, $v) => $q->where('verification_status', $v))
            ->when($request->group_id, fn($q, $id) => $q->whereHas('loans', fn($lq) => $lq->where('group_id', $id)))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('borrowers.index', compact('borrowers', 'groups'));
    }

    public function create()
    {
        $groups = TelegramGroup::where('status', 'active')->get();
        return view('borrowers.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'telegram_username' => 'nullable|string|max:255',
            'telegram_user_id' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Use shared BorrowerService — same logic as Telegram bot
        $result = $this->borrowerService->createBorrower(
            tenantId: auth()->user()->tenant_id,
            data: $validated,
            createdBy: auth()->user(),
            skipDuplicates: $request->boolean('force_create'),
        );

        // Handle duplicates
        if (!empty($result['duplicates'])) {
            $groups = TelegramGroup::where('status', 'active')->get();
            return redirect()->route('borrowers.create')
                ->withInput($request->all())
                ->with('duplicates', $result['duplicates']);
        }

        $borrower = $result['borrower'];

        return redirect()->route('borrowers.show', $borrower)
            ->with('success', "Borrower '{$borrower->name}' created successfully! Code: {$borrower->borrower_code}");
    }

    public function edit(Borrower $borrower)
    {
        return view('borrowers.edit', compact('borrower'));
    }

    public function update(Request $request, Borrower $borrower)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'telegram_username' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,blacklisted',
        ]);

        $borrower->update([
            'name' => $validated['name'],
            'telegram_username' => $validated['telegram_username'] ? ltrim($validated['telegram_username'], '@') : null,
            'phone_number' => $validated['phone_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()->route('borrowers.show', $borrower)
            ->with('success', "Borrower '{$borrower->name}' updated successfully.");
    }

    public function show(Borrower $borrower)
    {
        $borrower->load(['loans.group', 'loans.payments', 'creator']);
        $loansByGroup = $borrower->loans->groupBy(fn($loan) => $loan->group?->name ?? 'Unknown');

        $stats = [
            'total_loans' => $borrower->loans->count(),
            'active_loans' => $borrower->loans->whereIn('status', ['active', 'overdue'])->count(),
            'outstanding' => $borrower->loans->whereIn('status', ['active', 'overdue'])->sum('balance'),
            'overdue_count' => $borrower->loans->where('status', 'overdue')->count(),
            'total_paid' => $borrower->loans->flatMap->payments->where('status', 'approved')->sum('amount'),
            'last_payment' => $borrower->loans->flatMap->payments->where('status', 'approved')->sortByDesc('approved_at')->first(),
        ];

        return view('borrowers.show', compact('borrower', 'loansByGroup', 'stats'));
    }

    public function sendInvite(Borrower $borrower)
    {
        if (!$borrower->borrower_code) {
            $borrower->update(['borrower_code' => Borrower::generateCode()]);
        }

        return back()->with('success', 'Invite link ready! Share it with the borrower.');
    }

    public function unlinkTelegram(Borrower $borrower)
    {
        $this->borrowerService->unlinkTelegram($borrower);
        return back()->with('success', "Telegram account unlinked for '{$borrower->name}'.");
    }

    public function blacklist(Borrower $borrower)
    {
        $this->borrowerService->blacklist($borrower);
        return back()->with('success', "'{$borrower->name}' has been blacklisted.");
    }

    public function unblacklist(Borrower $borrower)
    {
        $this->borrowerService->unblacklist($borrower);
        return back()->with('success', "'{$borrower->name}' has been restored.");
    }

    /**
     * AJAX: Return group participants for the participant picker dropdown.
     */
    public function participants(TelegramGroup $group)
    {
        $participants = GroupParticipant::where('group_id', $group->id)
            ->where('is_bot', false)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn($p) => [
                'telegram_user_id' => $p->telegram_user_id,
                'username' => $p->telegram_username,
                'name' => $p->display_name,
                'last_seen' => $p->last_seen_at?->diffForHumans(),
            ]);

        return response()->json($participants);
    }

    /**
     * AJAX: Live "Trust Score Prediction" preview for the Add Borrower form.
     *
     * This is a rule-based completeness/validity heuristic, not a trained
     * model — there's no repayment history to train on for a borrower that
     * doesn't exist yet. It's a placeholder you can swap for a real model
     * later (e.g. once you can score against Loan/Payment history for
     * similar existing borrowers).
     */
    public function trustScorePreview(Request $request)
    {
        $name = trim((string) $request->input('name'));
        $phone = trim((string) $request->input('phone_number'));
        $telegram = trim((string) $request->input('telegram_username'));
        $address = trim((string) $request->input('address'));
        $notes = trim((string) $request->input('notes'));

        // Nothing filled in yet — don't show a score at all.
        if ($name === '' && $phone === '' && $telegram === '' && $address === '' && $notes === '') {
            return response()->json(['rate' => 0, 'label' => '']);
        }

        $score = 20; // baseline once the user starts typing

        if (str_word_count($name) >= 2) {
            $score += 20; // looks like a full name, not just a first name
        }

        $digitsOnly = preg_replace('/\D/', '', $phone);
        if (strlen($digitsOnly) >= 8) {
            $score += 15; // plausible phone number length
        }

        if ($telegram !== '') {
            $score += 15; // reachable via bot reminders
        }

        if (strlen($address) >= 10) {
            $score += 15; // detailed enough for geographic risk analysis
        }

        if ($notes !== '') {
            $score += 15; // extra context provided
        }

        $score = min(96, $score);

        $label = match (true) {
            $score >= 80 => 'High confidence — profile looks complete',
            $score >= 50 => 'Medium confidence — add more detail to improve this',
            default => 'Needs more info to generate a reliable estimate',
        };

        return response()->json([
            'rate' => $score,
            'label' => $label,
        ]);
    }
}
