<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->string('site_key', 64)->unique();
            $table->boolean('is_public')->default(false);
            $table->json('exclude_rules')->nullable();
            $table->unsignedTinyInteger('sample')->default(100);
            $table->boolean('validate_host')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'id']);
        });

        Schema::create('site_hosts', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('hostname');
            $table->timestamps();

            $table->unique(['site_id', 'hostname']);
            $table->index(['tenant_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_hosts');
        Schema::dropIfExists('sites');
    }
};
