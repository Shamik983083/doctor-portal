<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_pause_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->timestamp('paused_at');
            $table->timestamp('resumed_at')->nullable();  // null = currently paused
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['case_id', 'paused_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_pause_intervals');
    }
};
