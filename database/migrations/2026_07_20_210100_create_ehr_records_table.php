<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The outbox row for a clinical record pushed to an EHR (Healthie).
 *
 * Mirrors pharmacy_dispatches: the payload is stored on the row so an operator
 * can inspect exactly what would be sent while the integration is disabled, and
 * so a failed push can be retried from the same immutable material rather than
 * rebuilt from a case that may have moved on since.
 *
 * ADDITIVE ONLY. Creates one new table, touches nothing existing. This app runs
 * `artisan migrate --force` automatically on push to main and staging, so this
 * executes on merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ehr_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('clinical_note_id')->nullable()->constrained('clinical_notes')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();

            $table->string('adapter', 30);                    // mock | healthie
            $table->string('status', 20);                     // disabled | pending | sent | failed
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->json('payload');                          // Exactly what would be / was sent
            $table->string('reference')->nullable();          // Vendor record id on success
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // One record per case per note: approving twice must not create two
            // charts. The service also checks, but the constraint is what makes
            // it true under a double submit or a retried job.
            $table->unique(['case_id', 'clinical_note_id'], 'ehr_records_case_note_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ehr_records');
    }
};
