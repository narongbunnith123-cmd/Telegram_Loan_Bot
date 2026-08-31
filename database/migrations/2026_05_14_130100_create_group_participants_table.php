<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('telegram_groups')->cascadeOnDelete();
            $table->string('telegram_user_id');
            $table->string('telegram_username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'group_id', 'telegram_user_id']);
            $table->index(['tenant_id', 'telegram_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_participants');
    }
};
