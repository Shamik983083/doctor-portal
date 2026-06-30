<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0)->after('max_daily_cases');
        });

        // Seed initial priority by existing id order so all clinicians start with distinct ranks
        $ids = DB::table('clinicians')->orderBy('id')->pluck('id');
        foreach ($ids as $rank => $id) {
            DB::table('clinicians')->where('id', $id)->update(['priority' => $rank]);
        }
    }

    public function down(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
