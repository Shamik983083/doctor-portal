<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The instruction library the AI assist draws on: what "educating the bot"
 * actually means in a product. It is admin-owned prompt guidance, not model
 * training, so it is editable without a deploy and versioned so a note can be
 * traced back to the instructions that produced it.
 *
 * ADDITIVE ONLY. This creates new tables and touches nothing that exists.
 * Note that this app deploys with `artisan migrate --force` on push to main and
 * staging, so this runs automatically on merge; it is written to be safe under
 * that, but it deserves a deliberate review rather than a casual merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_instruction_sets', function (Blueprint $table) {
            $table->id();
            $table->string('context', 40);              // clinical_note | patient_message | storefront_message
            $table->string('name');                     // Human-readable, shown in the admin list
            $table->text('instructions');               // The guidance itself
            $table->string('tone', 120)->nullable();    // Short style steer, e.g. "Plain, warm, no jargon"
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One ACTIVE set per context is the rule the service relies on when
            // it resolves which instructions to use. Enforced in the model and
            // the controller rather than as a partial index, because MySQL has
            // no partial unique index; the index below just keeps lookups cheap.
            $table->index(['context', 'is_active']);
        });

        /**
         * Worked examples belong to a set. Kept as rows rather than a JSON blob
         * so the admin screen can add, edit and remove one example at a time and
         * so a bad example can be deactivated without editing the whole set.
         */
        Schema::create('ai_instruction_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_instruction_set_id')->constrained()->cascadeOnDelete();
            $table->text('situation');                  // When this applies
            $table->text('good_output');                // What a good draft looks like
            $table->text('bad_output')->nullable();     // Optional counter-example
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now()->toDateTimeString();

        /*
         * Seeded with the behaviour the design preview already demonstrates, so
         * the app starts in the state Devin has been reviewing rather than empty.
         * These are starting points for the admin to edit, not fixed rules.
         */
        DB::table('ai_instruction_sets')->insert([
            [
                'context'      => 'clinical_note',
                'name'         => 'Default clinical note',
                'instructions' => "Draft a concise clinical note for the reviewing provider to edit and sign.\n"
                    . "Use only the information in this case's record and the decisions the provider has just made.\n"
                    . "State facts, not conclusions the record does not support. Never invent history, measurements or dates.\n"
                    . "If the provider has already written text, treat it as the steer: keep their words, build around them, and do not repeat back anything they have already said.\n"
                    . "Name every approved medication with its term, frequency, dose ladder and refills. Name every declined medication as declined.\n"
                    . "Do not sign the note, do not address the patient, and do not give the provider instructions.",
                'tone'         => 'Clinical, plain, no filler',
                'version'      => 1, 'is_active' => 1, 'sort_order' => 10,
                'updated_by'   => null, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'context'      => 'patient_message',
                'name'         => 'Default patient reply',
                'instructions' => "Draft a reply to the patient for the provider to edit and send.\n"
                    . "Read every message the patient has sent in the thread, not only the most recent one. The last message is often a thank-you, and answering that instead of their actual question is worse than not replying.\n"
                    . "Plain language, sixth to eighth grade reading level. No clinical jargon.\n"
                    . "Never promise a clinical outcome, never diagnose, and never state or imply that a decision has been made when it has not.\n"
                    . "If the case is on hold, say plainly that it is not lost and what is being waited on.\n"
                    . "Do not sign off with a provider's name. The provider sends it, so the sign-off is theirs.",
                'tone'         => 'Warm, direct, reassuring without over-promising',
                'version'      => 1, 'is_active' => 1, 'sort_order' => 20,
                'updated_by'   => null, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'context'      => 'storefront_message',
                'name'         => 'Default storefront reply',
                'instructions' => "Draft a reply to the storefront partner for a team member to edit and send.\n"
                    . "Business tone, not clinical. Share only what the partner is entitled to see: case status, what is blocking it and what is needed next.\n"
                    . "Never include clinical detail, intake answers, diagnoses or medication specifics in a partner message.\n"
                    . "Be specific about what the partner has to do, if anything.",
                'tone'         => 'Professional, brief, operational',
                'version'      => 1, 'is_active' => 1, 'sort_order' => 30,
                'updated_by'   => null, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $noteSetId = DB::table('ai_instruction_sets')->where('context', 'clinical_note')->value('id');
        $msgSetId  = DB::table('ai_instruction_sets')->where('context', 'patient_message')->value('id');

        DB::table('ai_instruction_examples')->insert([
            [
                'ai_instruction_set_id' => $noteSetId,
                'situation'   => 'The provider approved the primary medication and declined the add-on.',
                'good_output' => 'Approved Semaglutide, 3 months, weekly, dosing L1 2.5 mg then L2 5 mg then L3 7.5 mg, 0 refills. Declined Zofran at this time.',
                'bad_output'  => 'Approved the requested regimen. Add-on not approved.',
                'sort_order'  => 10, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'ai_instruction_set_id' => $noteSetId,
                'situation'   => 'The provider has already typed their own basics before asking for a draft.',
                'good_output' => 'Their sentence is kept as written and leads the note, with the record composed around it and nothing they already said repeated.',
                'bad_output'  => 'The draft replaces what they typed with a fresh composition.',
                'sort_order'  => 20, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'ai_instruction_set_id' => $msgSetId,
                'situation'   => 'The patient says the video visit link will not open and asks to be called.',
                'good_output' => 'Sorry the link is not opening. I can arrange for someone to call you instead and book the visit over the phone. Would later today or tomorrow morning suit you better?',
                'bad_output'  => 'Please try clearing your browser cache and using a different device.',
                'sort_order'  => 10, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_instruction_examples');
        Schema::dropIfExists('ai_instruction_sets');
    }
};
