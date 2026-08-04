<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_hourly_totals', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id'); $table->timestamp('hour');
            $table->unsignedBigInteger('pageviews')->default(0); $table->unsignedBigInteger('visitors')->default(0); $table->unsignedBigInteger('sessions')->default(0); $table->unsignedBigInteger('bounces')->default(0); $table->unsignedBigInteger('duration_sum')->default(0);
            $table->unique(['site_id', 'hour']);
        });
        Schema::create('stats_hourly_pages', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id'); $table->timestamp('hour'); $table->string('path'); $table->unsignedBigInteger('pageviews')->default(0); $table->unsignedBigInteger('visitors')->default(0); $table->unsignedBigInteger('bounces')->default(0); $table->unsignedBigInteger('duration_sum')->default(0); $table->unique(['site_id', 'hour', 'path']);
        });
        foreach (['referrers' => ['referrer'], 'countries' => ['country'], 'devices' => ['device', 'browser', 'os'], 'campaigns' => ['source', 'medium', 'campaign'], 'events' => ['event_name']] as $name => $dimensions) {
            Schema::create('stats_hourly_'.$name, function (Blueprint $table) use ($dimensions, $name): void {
                $table->unsignedBigInteger('site_id'); $table->timestamp('hour');
                foreach ($dimensions as $dimension) $table->string($dimension);
                $table->unsignedBigInteger($name === 'events' ? 'count' : 'pageviews')->default(0); $table->unsignedBigInteger('visitors')->default(0);
                $table->unique(array_merge(['site_id', 'hour'], $dimensions), 'stats_hourly_'.$name.'_dimension_unique');
            });
        }
        Schema::create('session_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id'); $table->string('visitor_id', 16); $table->date('day'); $table->timestamp('hour'); $table->timestamp('last_event_at'); $table->timestamp('last_pageview_at')->nullable(); $table->unsignedInteger('pageviews')->default(0);
            $table->unique(['site_id', 'visitor_id', 'day']);
        });
    }

    public function down(): void { Schema::dropIfExists('session_states'); foreach (['events','campaigns','devices','countries','referrers','pages','totals'] as $table) Schema::dropIfExists('stats_hourly_'.$table); }
};
