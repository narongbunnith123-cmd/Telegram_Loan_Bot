<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL uses the composite unique index for the tenant_id FK constraint.
        // Must add a standalone index for tenant_id first so the FK still works.
        DB::statement('ALTER TABLE `borrowers` ADD INDEX `borrowers_tenant_id_index` (`tenant_id`)');
        DB::statement('ALTER TABLE `borrowers` DROP INDEX `borrowers_tenant_id_telegram_user_id_group_id_unique`');
        DB::statement('ALTER TABLE `borrowers` DROP INDEX `borrowers_group_id_foreign`');
        DB::statement('ALTER TABLE `borrowers` DROP COLUMN `group_id`');

        // Add new columns and constraints
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('telegram_user_id')->nullable()->change();

            $table->string('phone_number', 50)->nullable()->after('name');
            $table->text('address')->nullable()->after('phone_number');
            $table->string('borrower_code', 20)->nullable()->unique()->after('telegram_username');
            $table->string('verification_status', 20)->default('pending')->after('status');
            $table->string('onboarding_source', 30)->default('manual')->after('verification_status');
            $table->timestamp('linked_at')->nullable()->after('onboarding_source');
            $table->unsignedBigInteger('created_by')->nullable()->after('linked_at');
            $table->softDeletes();

            $table->unique(['tenant_id', 'telegram_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'telegram_user_id']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone_number', 'address', 'borrower_code',
                'verification_status', 'onboarding_source',
                'linked_at', 'created_by',
            ]);
            $table->string('telegram_user_id')->nullable(false)->change();
            $table->foreignId('group_id')->after('tenant_id')->constrained('telegram_groups');
            $table->unique(['tenant_id', 'telegram_user_id', 'group_id']);
        });
    }
};
