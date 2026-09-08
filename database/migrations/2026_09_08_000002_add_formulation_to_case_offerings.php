<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_offerings', function (Blueprint $table) {
            $table->string('formulation')->nullable()->after('bundle_group');
        });
    }

    public function down(): void
    {
        Schema::table('case_offerings', function (Blueprint $table) {
            $table->dropColumn('formulation');
        });
    }
};
