<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_user_id')->nullable()->after('is_super_admin');
            $table->string('telegram_link_code', 8)->nullable()->after('telegram_user_id');
            $table->timestamp('telegram_link_expires_at')->nullable()->after('telegram_link_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_user_id', 'telegram_link_code', 'telegram_link_expires_at']);
        });
    }
};
