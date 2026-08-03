<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('sessions', 'ip_address')) {
                $table->dropColumn('ip_address');
            }

            if (Schema::hasColumn('sessions', 'user_agent')) {
                $table->dropColumn('user_agent');
            }
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring removed request-identity storage would violate the privacy invariant.
    }
};
