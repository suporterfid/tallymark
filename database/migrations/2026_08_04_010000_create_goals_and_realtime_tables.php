<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('event_name')->nullable();
            $table->string('url_pattern')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'event_name']);
        });

        Schema::create('stats_hourly_goals', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('goal_id');
            $table->timestamp('hour');
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unique(['site_id', 'hour', 'goal_id']);
        });

        Schema::create('stats_daily_goals', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('goal_id');
            $table->date('day');
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unique(['site_id', 'day', 'goal_id']);
        });

        Schema::create('stats_realtime_five_minutes', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id');
            $table->timestamp('bucket');
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('events')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unique(['site_id', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_realtime_five_minutes');
        Schema::dropIfExists('stats_daily_goals');
        Schema::dropIfExists('stats_hourly_goals');
        Schema::dropIfExists('goals');
    }
};
