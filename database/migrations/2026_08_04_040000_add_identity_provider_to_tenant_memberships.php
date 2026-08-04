<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->string('identity_provider')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropColumn('identity_provider');
        });
    }
};
