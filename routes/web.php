<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Tenant\BorrowerController;
use App\Http\Controllers\Tenant\BotSetupController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\GroupController;
use App\Http\Controllers\Tenant\LoanController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\ReminderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', DashboardController::class)->name('dashboard');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::patch('/groups/{group}/approve', [GroupController::class, 'approve'])->name('groups.approve');
    Route::patch('/groups/{group}/suspend', [GroupController::class, 'suspend'])->name('groups.suspend');
    Route::patch('/groups/{group}/unsuspend', [GroupController::class, 'unsuspend'])->name('groups.unsuspend');

    // Borrowers
    Route::get('/borrowers', [BorrowerController::class, 'index'])->name('borrowers.index');
    Route::get('/borrowers/create', [BorrowerController::class, 'create'])->name('borrowers.create');
    Route::post('/borrowers', [BorrowerController::class, 'store'])->name('borrowers.store');
    Route::post('/borrowers/trust-score-preview', [BorrowerController::class, 'trustScorePreview'])->name('borrowers.trust-score-preview');
    Route::get('/borrowers/{borrower}', [BorrowerController::class, 'show'])->name('borrowers.show');
    Route::get('/borrowers/{borrower}/edit', [BorrowerController::class, 'edit'])->name('borrowers.edit');
    Route::patch('/borrowers/{borrower}', [BorrowerController::class, 'update'])->name('borrowers.update');
    Route::post('/borrowers/{borrower}/invite', [BorrowerController::class, 'sendInvite'])->name('borrowers.invite');
    Route::delete('/borrowers/{borrower}/unlink', [BorrowerController::class, 'unlinkTelegram'])->name('borrowers.unlink');
    Route::patch('/borrowers/{borrower}/blacklist', [BorrowerController::class, 'blacklist'])->name('borrowers.blacklist');
    Route::patch('/borrowers/{borrower}/unblacklist', [BorrowerController::class, 'unblacklist'])->name('borrowers.unblacklist');
    Route::get('/api/groups/{group}/participants', [BorrowerController::class, 'participants'])->name('api.participants');

    // Loans
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::get('/loans/{loan}/edit', [LoanController::class, 'edit'])->name('loans.edit');
    Route::patch('/loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
    Route::patch('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');
    Route::patch('/loans/{loan}/installments/{installment}/waive-penalty', [LoanController::class, 'waivePenalty'])->name('loans.waive-penalty');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::get('/payments/sessions', [PaymentController::class, 'sessions'])->name('payments.sessions');
    Route::get('/payments/sessions/{session}', [PaymentController::class, 'sessionDetail'])->name('payments.session-detail');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::patch('/payments/{payment}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::patch('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

    // Reminders
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::get('/reminders/settings', [ReminderController::class, 'settings'])->name('reminders.settings');
    Route::patch('/reminders/templates/{template}', [ReminderController::class, 'updateTemplate'])->name('reminders.update-template');
    Route::patch('/reminders/rules/{rule}', [ReminderController::class, 'updateRule'])->name('reminders.update-rule');
    Route::post('/reminders/templates/{template}/preview', [ReminderController::class, 'previewTemplate'])->name('reminders.preview-template');
    Route::post('/reminders/templates/{template}/test-send', [ReminderController::class, 'testSend'])->name('reminders.test-send');
    Route::post('/reminders/strategy', [ReminderController::class, 'applyStrategy'])->name('reminders.apply-strategy');
    Route::post('/reminders/manual-send', [ReminderController::class, 'manualSend'])->name('reminders.manual-send');
    Route::post('/reminders/process-due', [ReminderController::class, 'processDue'])->name('reminders.process-due');
    Route::get('/reminders/borrowers', [ReminderController::class, 'borrowersForGroup'])->name('reminders.borrowers');

    // Bot Setup
    Route::get('/bot/setup', function () {
        $botToken = \App\Models\BotToken::where('tenant_id', auth()->user()->tenant_id)->first();
        return view('bot.setup', compact('botToken'));
    })->name('bot.setup');
    Route::post('/bot/register', [BotSetupController::class, 'register'])->name('bot.register');
    Route::post('/bot/reregister', [BotSetupController::class, 'register'])->name('bot.reregister');
    Route::delete('/bot/disconnect', function () {
        \App\Models\BotToken::where('tenant_id', auth()->user()->tenant_id)->delete();
        return back()->with('success', 'Bot disconnected.');
    })->name('bot.disconnect');

    // Telegram Account Linking
    Route::post('/bot/link-code', [BotSetupController::class, 'generateLinkCode'])->name('bot.link-code');
    Route::delete('/bot/unlink', [BotSetupController::class, 'unlinkTelegram'])->name('bot.unlink');
});

// TEMPORARY ROUTE FOR DEBUGGING
Route::get('/force-seed', function () {
    $output = [];
    try {
        // Force mysql and array cache to avoid permission issues
        config(['database.default' => 'mysql']);
        config(['cache.default' => 'array']);
        \Illuminate\Support\Facades\DB::purge('mysql');
        $output[] = "Forced mysql + array cache.";

        // Test the connection
        $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
        $output[] = "Connected! Tables found: " . count($tables);

        // Run pending migrations only (skip already-done ones)
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output[] = "Migrations: OK";
        } catch (\Exception $e) {
            $output[] = "Migration note: " . \Illuminate\Support\Str::limit($e->getMessage(), 100);
        }

        // Create tenant directly
        $tenant = \App\Models\Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default', 'status' => 'active']
        );
        $output[] = "Tenant: ID={$tenant->id}, slug={$tenant->slug}";

        // Create admin user directly
        $user = \App\Models\User::where('email', 'admin@loanbot.com')->first();
        if (!$user) {
            $user = \App\Models\User::create([
                'tenant_id'      => $tenant->id,
                'name'           => 'Admin',
                'email'          => 'admin@loanbot.com',
                'password'       => \Illuminate\Support\Facades\Hash::make('password'),
                'is_super_admin' => true,
            ]);
            $output[] = "Admin user CREATED. ID: {$user->id}";
        } else {
            $user->password = \Illuminate\Support\Facades\Hash::make('password');
            $user->save();
            $output[] = "Admin user EXISTS. ID: {$user->id}. Password reset.";
        }

        // Create roles & permissions
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
            if (!$user->hasRole('super_admin')) {
                $user->assignRole('super_admin');
            }
            $output[] = "Role super_admin assigned.";
        } catch (\Exception $e) {
            $output[] = "Role error: " . $e->getMessage();
        }

        // Re-cache config with correct mysql connection
        config(['database.default' => 'mysql']);
        config(['cache.default' => 'file']);
        \Illuminate\Support\Facades\Artisan::call('config:cache');
        $output[] = "Config re-cached with mysql.";

        $output[] = "";
        $output[] = "✅ DONE! Now go to /login and use: admin@loanbot.com / password";

    } catch (\Exception $e) {
        $output[] = "FATAL Error: " . $e->getMessage();
    }
    return '<pre>' . implode("\n", $output) . '</pre>';
});
