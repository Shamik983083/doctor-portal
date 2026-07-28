<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor — nullable so console/queue/seeder writes don't crash.
            // actor_name is denormalised: survives user deletion.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 200)->nullable();

            // What happened
            $table->string('action', 20); // created | updated | deleted | restored | force_deleted

            // Which record
            $table->string('auditable_type', 80);   // short class basename, e.g. "PatientCase"
            $table->unsignedBigInteger('auditable_id');
            $table->string('auditable_label', 250)->nullable(); // human label at write time

            // Payload — only dirty fields for 'updated'; full attrs for 'created'; null for others.
            $table->json('diff')->nullable();

            // Request path or "console" — helps trace which UI action triggered the event.
            $table->string('context', 500)->nullable();

            // Audit entries are immutable — no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'al_auditable');
            $table->index('actor_id', 'al_actor');
            $table->index('created_at', 'al_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
