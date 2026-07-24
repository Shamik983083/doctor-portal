<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_events', function (Blueprint $table) {
            // F20: identifies the automated service that triggered an event
            // (e.g. 'karen') so audit logs can attribute system actions to
            // a named actor rather than logging them as bare 'system' rows.
            $table->string('actor_service')->nullable()->after('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('case_events', function (Blueprint $table) {
            $table->dropColumn('actor_service');
        });
    }
};
