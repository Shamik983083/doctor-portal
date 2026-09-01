<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            // Set when a case auto-releases because the provider missed their
            // completion deadline. They cannot pull from the pool until this clears.
            $table->timestamp('pool_cooldown_until')->nullable()->after('cases_last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->dropColumn('pool_cooldown_until');
        });
    }
};
