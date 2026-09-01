<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_prescription_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_prescription_id')->constrained()->cascadeOnDelete();
            $table->string('icd_code', 30);
            $table->string('description', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_prescription_diagnoses');
    }
};
