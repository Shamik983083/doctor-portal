<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // DECIMAL(4,2) caps at 99.99 — BMI ≥ 100 causes MySQL error 1264.
    // Widen to DECIMAL(5,2) to support up to 999.99.

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->decimal('bmi', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->decimal('bmi', 4, 2)->nullable()->change();
        });
    }
};
