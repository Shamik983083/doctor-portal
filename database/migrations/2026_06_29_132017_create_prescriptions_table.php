<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('clinician_id')->constrained('clinicians')->cascadeOnDelete();
            $table->text('diagnoses');
            $table->text('directions')->nullable();
            $table->text('medical_necessity')->nullable();
            $table->timestamp('prescribed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_prescriptions');
    }
};
