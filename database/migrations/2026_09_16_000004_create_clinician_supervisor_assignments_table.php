<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_physician_id')->constrained('supervisor_physicians')->cascadeOnDelete();
            $table->char('state', 2);
            $table->timestamps();

            // One supervisor per state per clinician.
            $table->unique(['clinician_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_supervisor_assignments');
    }
};
