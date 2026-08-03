<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('filename')->unique();
            $table->string('status')->default('processing');
            $table->string('claim_token', 36)->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->unsignedInteger('accepted_lines')->default(0);
            $table->unsignedInteger('malformed_lines')->default(0);
            $table->unsignedInteger('next_line')->default(0);
            $table->timestamp('staged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ingest_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingest_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['ingest_batch_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_events');
        Schema::dropIfExists('ingest_batches');
    }
};
