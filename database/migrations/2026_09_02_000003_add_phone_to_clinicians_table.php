<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('npi');
        });
    }

    public function down(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
