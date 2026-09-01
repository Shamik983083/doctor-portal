<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_prescription_medications', function (Blueprint $table) {
            $table->text('sig')->nullable()->after('days_until_dispense');
        });
    }

    public function down(): void
    {
        Schema::table('case_prescription_medications', function (Blueprint $table) {
            $table->dropColumn('sig');
        });
    }
};
