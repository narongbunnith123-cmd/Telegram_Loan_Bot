<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telegram_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_user_id');
            $table->string('telegram_chat_id');
            $table->string('current_action')->nullable()->comment('e.g. create_borrower, create_loan, record_payment');
            $table->string('current_step')->nullable()->comment('e.g. select_group, enter_name, enter_amount');
            $table->json('temp_data')->nullable()->comment('Accumulated form data during conversation');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One active session per user per chat
            $table->unique(['tenant_id', 'telegram_user_id', 'telegram_chat_id'], 'tg_session_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_sessions');
    }
};
