<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignId('offering_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            // pending, submitted, approved, declined, cancelled
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->integer('refills')->default(0);
            $table->text('clinician_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_offerings');
    }
};
