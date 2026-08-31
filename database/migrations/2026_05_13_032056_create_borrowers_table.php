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
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('telegram_groups');
            $table->string('telegram_user_id');
            $table->string('telegram_username')->nullable();
            $table->string('name');
            $table->enum('status', ['active', 'blacklisted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'telegram_user_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
