<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company EHR credentials and identifiers.
 *
 * THIS TABLE IS THE SEGREGATION MECHANISM. Healthie data must never cross
 * storefronts, and the strongest way to guarantee that is for each company to
 * push with its OWN credential, so a cross-tenant read is impossible rather than
 * merely incorrect. One row per partner per provider; there is no global
 * fallback credential on purpose, because a global key is exactly what would let
 * one company's approval land in another company's chart.
 *
 * A separate table rather than columns on `partners` because these are secrets
 * with a different lifecycle (rotated, revoked, environment-specific) and should
 * not be loaded every time a partner is read.
 *
 * ADDITIVE ONLY. This app runs `artisan migrate --force` on push to main and
 * staging, so it executes on merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_ehr_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();

            // Which EHR these values are for. One row per provider per partner,
            // so moving a company to a different EHR later is additive.
            $table->string('provider', 30)->default('healthie');

            /*
             * Healthie authentication, per their docs:
             *   Authorization: Basic <api_key>
             *   AuthorizationSource: API
             *   AuthorizationShard: <shard>   (only when the account is sharded)
             *
             * api_key is encrypted at rest via the model cast. It is a credential
             * that can create and read patient records, so it is treated like one.
             */
            $table->text('api_key')->nullable();
            $table->string('authorization_shard')->nullable();

            // Endpoint is per-environment (staging vs production are different
            // hosts) and is deliberately configurable per company rather than a
            // constant, so one storefront can be piloted before the rest move.
            $table->string('endpoint')->nullable();

            /*
             * Vendor-side identifiers this company's records must carry. Named
             * for what they are rather than guessed at Healthie's exact schema:
             * see docs/integrations/HEALTHIE-SETUP.md for how to fill these in.
             */
            $table->string('organization_id')->nullable();      // Healthie org/company this data belongs to
            $table->string('default_provider_id')->nullable();  // Healthie user the note is attributed to
            $table->string('note_form_id')->nullable();         // Custom module form the note becomes

            // Same two-flag posture as everything else that talks outward.
            $table->boolean('is_enabled')->default(false);
            $table->boolean('sandbox_validated')->default(false);

            $table->timestamps();

            // One settings row per partner per provider.
            $table->unique(['partner_id', 'provider'], 'partner_ehr_settings_partner_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ehr_settings');
    }
};
