<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinicians', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('npi')->nullable()->unique();
            $table->string('license_number')->nullable();
            $table->string('license_state')->nullable();
            $table->string('specialty')->nullable();
            $table->string('credentials')->nullable(); // MD, DO, NP, PA
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->boolean('is_available')->default(true);
            $table->integer('max_daily_cases')->default(20);
            $table->json('licensed_states')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinicians');
    }
};
