<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add webhook-related fields after penalty_paid column
            $table->string('reference_code')->nullable()->index()->after('penalty_paid');
            $table->string('transaction_id')->nullable()->index()->after('reference_code');
            $table->string('gateway_name')->nullable()->after('method');
            $table->timestamp('paid_at')->nullable()->after('approved_at');
            
            // Add unique index on (tenant_id, reference_code) to ensure unique reference codes within tenant
            $table->unique(['tenant_id', 'reference_code'], 'unique_tenant_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop unique index first
            $table->dropUnique('unique_tenant_reference');
            
            // Drop columns
            $table->dropColumn([
                'reference_code',
                'transaction_id',
                'gateway_name',
                'paid_at'
            ]);
        });
    }
};
