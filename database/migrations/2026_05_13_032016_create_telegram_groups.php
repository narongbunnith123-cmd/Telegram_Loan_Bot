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
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_group_id');        // Telegram's chat ID (can be negative)
            $table->string('name');
            $table->enum('status', ['pending', 'approved', 'active', 'suspended', 'disabled'])
                ->default('pending');
            $table->json('settings')->nullable();       // interest_rate, reminder_frequency, currency
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'telegram_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};
