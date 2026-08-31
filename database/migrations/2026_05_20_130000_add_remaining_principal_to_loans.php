<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('remaining_principal', 15, 2)->default(0)->after('balance');
            $table->decimal('accrued_interest', 15, 2)->default(0)->after('remaining_principal');
            $table->decimal('daily_interest_rate', 10, 6)->default(0)->after('accrued_interest');
        });

        // Seed existing loans with correct values
        $loans = DB::table('loans')->whereIn('status', ['active', 'overdue'])->get();
        foreach ($loans as $loan) {
            $totalPaid = DB::table('payments')
                ->where('loan_id', $loan->id)
                ->where('status', 'approved')
                ->sum('amount');

            $totalInterest = DB::table('loan_interest_logs')
                ->where('loan_id', $loan->id)
                ->sum('interest_applied');

            // remaining_principal = principal - (payments that went to principal)
            $paidToPrincipal = max(0, $totalPaid - $totalInterest);
            $remainingPrincipal = max(0, $loan->principal - $paidToPrincipal);

            // Calculate daily_interest_rate from interest_value
            $dailyRate = 0;
            if ($loan->principal > 0) {
                if ($loan->interest_type === 'fixed') {
                    // Convert: $2/day on $1000 → 0.002 rate
                    $dailyRate = $loan->interest_value / $loan->principal;
                } else {
                    // Percentage: 10% → 0.10
                    $dailyRate = $loan->interest_value / 100;
                }
            }

            DB::table('loans')->where('id', $loan->id)->update([
                'remaining_principal' => $remainingPrincipal,
                'accrued_interest'    => $totalInterest,
                'daily_interest_rate' => $dailyRate,
            ]);
        }

        // Also set remaining_principal for paid/completed loans
        DB::table('loans')->whereIn('status', ['paid', 'completed'])->update([
            'remaining_principal' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['remaining_principal', 'accrued_interest', 'daily_interest_rate']);
        });
    }
};
