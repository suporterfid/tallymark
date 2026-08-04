<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->boolean('is_enabled')->default(true)->after('url_pattern');
        });

        DB::table('goals')->whereNull('public_id')->orderBy('id')->each(function (object $goal): void {
            DB::table('goals')->where('id', $goal->id)->update(['public_id' => 'goal_'.Str::ulid()->toBase32()]);
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'is_enabled']);
        });
    }
};
