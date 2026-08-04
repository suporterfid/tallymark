<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taskconnect_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('tick_id', 32);
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 64);
            $table->string('period', 64);
            $table->string('task_name');
            $table->string('target_url');
            $table->string('site_public_id');
            $table->string('goal_public_id');
            $table->unsignedBigInteger('conversion_count');
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('claim_token', 32)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('task_id')->nullable();
            $table->string('task_url')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at', 'id']);
            $table->unique(['kind', 'tick_id', 'goal_id', 'period'], 'tc_submissions_tick_goal_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taskconnect_submissions');
    }
};
