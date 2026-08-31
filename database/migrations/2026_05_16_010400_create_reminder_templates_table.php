<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('reminder_type', ['before_due', 'due_today', 'overdue', 'escalation', 'admin_alert']);
            $table->enum('target_type', ['dm', 'group', 'admin']);
            $table->enum('tone', ['gentle', 'balanced', 'aggressive'])->default('balanced');
            $table->text('message_template');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'reminder_type', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_templates');
    }
};
