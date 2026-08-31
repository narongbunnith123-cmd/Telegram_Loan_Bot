<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('reminder_type', ['before_due', 'due_today', 'overdue', 'escalation']);
            $table->integer('days_offset'); // -3 = 3 days before, 0 = due today, 3 = 3 days overdue
            $table->enum('frequency_type', ['once', 'daily', 'every_2_days', 'weekly'])->default('once');
            $table->boolean('send_to_dm')->default(true);
            $table->boolean('send_to_group')->default(false);
            $table->boolean('send_to_admin')->default(false);
            $table->foreignId('template_id')->nullable()->constrained('reminder_templates')->nullOnDelete();
            $table->unsignedInteger('cooldown_hours')->default(12);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
            $table->index(['reminder_type', 'days_offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_rules');
    }
};
