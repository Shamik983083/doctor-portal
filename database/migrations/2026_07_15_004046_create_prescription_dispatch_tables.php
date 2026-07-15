<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MA-DOCPORTAL fundamentals — prescription document + pharmacy dispatch outbox.
 *
 * `prescription_documents` is APPEND-ONLY / immutable: one signed PDF generated
 * from the locked provider-approved order snapshot, with a content hash. It is
 * never updated (a correction is a new document).
 *
 * `pharmacy_dispatches` is the outbox for pushing an order to a pharmacy gateway,
 * with retry/backoff/dead-letter — modeled on the existing webhook_deliveries.
 *
 * Rollback: drop both tables (the PDFs on disk are orphaned but harmless).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('case_prescription_id')->constrained('case_prescriptions')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('clinician_id')->nullable()->constrained('clinicians')->nullOnDelete();

            // The locked provider-approved order snapshot the PDF was rendered from.
            $table->json('snapshot');
            $table->string('document_path');          // path on the documents disk
            $table->string('content_hash', 64);       // sha256 of the PDF bytes
            $table->text('attestation');
            $table->timestamp('attested_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'created_at']);
        });

        Schema::create('pharmacy_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prescription_document_id')->constrained('prescription_documents')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();

            $table->string('adapter');                // mock | lifefile | ...
            // disabled = feature-flagged off (preview); pending|sending|sent|failed|dead_letter
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->json('payload')->nullable();      // the built POST /order body
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('external_ref')->nullable(); // pharmacy order id on success
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_dispatches');
        Schema::dropIfExists('prescription_documents');
    }
};
