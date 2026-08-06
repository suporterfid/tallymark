<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_dashboards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('public_id')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_dashboards');
    }
};
