<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salts', function (Blueprint $table): void {
            $table->id();
            $table->date('active_on')->unique();
            $table->string('value', 64);
            $table->timestamp('destroy_at');
            $table->timestamps();
        });

        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('status')->default('healthy');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_error_at')->nullable();
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('salts');
    }
};
