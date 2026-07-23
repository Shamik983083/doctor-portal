<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinical intake block the storefront pushes for a case (Devin msg 2258:
 * "They send to us" + "Everything from the preview in that exact format").
 *
 * The provider review queue in the design preview shows a column set the app
 * does not capture today: requested dose ladder, term, titrate plan, additional
 * meds 2 to 4, on-GLP, standard Zofran, and an allergy flag with the patient's
 * own words. The requested MEDICATION already exists as the offering on the
 * case; everything else in that grid comes from the storefront's intake and has
 * nowhere to live until now.
 *
 * ONE JSON COLUMN, holding the preview's exact shape, because that is what Devin
 * asked to display and it keeps the storefront contract in one place rather than
 * a dozen columns that each need a migration when the grid changes. The queue
 * reads it; the Partner API writes it (on case create, or via
 * POST /api/partner/cases/{id}/clinical afterwards).
 *
 * Stored shape (all optional, mirrors docs/design-preview QUEUE rows):
 *   {
 *     "product": "Semaglutide", "dose": "L1 · 2.5 mg", "term": "3M",
 *     "plan": "Titration", "med2": "Zofran", "med3": "-", "med4": "-",
 *     "onGlp": "N", "zofran": "Y", "allergy": "N", "allergyDetail": null,
 *     "video": "Clear", "protocolVersion": "GLP-1 protocol v8",
 *     "findings": [...], "summary": [...], "sourceAnswers": {...}
 *   }
 *
 * NULLABLE, so every existing case and every case from a storefront not yet
 * sending this reads as "no clinical intake" and the columns show a dash rather
 * than a fabricated value.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->json('clinical_intake')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('clinical_intake');
        });
    }
};
