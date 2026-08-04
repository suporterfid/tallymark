<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_daily_totals', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id');
            $table->date('day');
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('bounces')->default(0);
            $table->unsignedBigInteger('duration_sum')->default(0);
            $table->unique(['site_id', 'day']);
        });

        Schema::create('stats_daily_pages', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id');
            $table->date('day');
            $table->string('path');
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('bounces')->default(0);
            $table->unsignedBigInteger('duration_sum')->default(0);
            $table->unique(['site_id', 'day', 'path']);
        });

        foreach (['referrers' => ['referrer'], 'countries' => ['country'], 'devices' => ['device', 'browser', 'os'], 'campaigns' => ['source', 'medium', 'campaign'], 'events' => ['event_name']] as $name => $dimensions) {
            Schema::create('stats_daily_'.$name, function (Blueprint $table) use ($dimensions, $name): void {
                $table->unsignedBigInteger('site_id');
                $table->date('day');
                foreach ($dimensions as $dimension) {
                    $table->string($dimension);
                }
                $table->unsignedBigInteger($name === 'events' ? 'count' : 'pageviews')->default(0);
                $table->unsignedBigInteger('visitors')->default(0);
                $table->unique(array_merge(['site_id', 'day'], $dimensions), 'stats_daily_'.$name.'_dimension_unique');
            });
        }

        Schema::create('daily_visitors', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id');
            $table->date('day');
            $table->string('visitor_id', 16);
            $table->unique(['site_id', 'day', 'visitor_id']);
            $table->index(['site_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_visitors');
        foreach (['events', 'campaigns', 'devices', 'countries', 'referrers', 'pages', 'totals'] as $table) {
            Schema::dropIfExists('stats_daily_'.$table);
        }
    }
};
