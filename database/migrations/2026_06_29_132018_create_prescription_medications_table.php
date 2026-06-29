<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_prescription_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('compound_formula')->nullable();
            $table->unsignedSmallInteger('refills')->nullable();
            $table->decimal('quantity', 8, 2)->nullable();
            $table->unsignedSmallInteger('days_supply')->nullable();
            $table->string('dispense_unit')->nullable();
            $table->unsignedSmallInteger('days_until_dispense')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_prescription_medications');
    }
};
