<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned routing policy, ported from MA-DOCPORTAL's `RoutingPolicy` model.
 *
 * MA's shape carried over: version, mode, a JSON config, DRAFT/ACTIVE/SUPERSEDED
 * status, activation timestamp and actor, unique on version.
 *
 * WHY VERSIONED RATHER THAN A SETTINGS ROW: routing decides which doctor sees which
 * patient. When someone asks six months from now why a case went where it did, a
 * mutable settings row cannot answer. A superseded version can.
 *
 * The seeded row reproduces MEDAXIS's EXISTING behaviour (PRIORITY mode) and is
 * ACTIVE from the start, so this migration changes no routing outcome on the day it
 * runs. Switching modes is a deliberate act afterwards.
 *
 * ADDITIVE ONLY. This app runs `artisan migrate --force` on push to main and
 * staging, so it executes on merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('mode', 30);                 // PRIORITY | ROUND_ROBIN | WEIGHTED | INTELLIGENT | PROVIDER_POOL
            $table->json('config')->nullable();         // intelligentWeights + providerWeights + messageAgingThresholdHours
            $table->string('status', 20)->default('DRAFT');   // DRAFT | ACTIVE | SUPERSEDED
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();           // why this version exists
            $table->timestamps();

            $table->index(['status', 'version']);
        });

        // Seed the current behaviour as version 1, ACTIVE. Routing is unchanged
        // until someone activates a different version on purpose.
        DB::table('routing_policies')->insert([
            'version'      => 1,
            'mode'         => 'PRIORITY',
            'config'       => json_encode([
                'intelligentWeights'          => (object) [],
                'providerWeights'             => (object) [],
                'messageAgingThresholdHours'  => null,
            ]),
            'status'       => 'ACTIVE',
            'activated_at' => now(),
            'created_by'   => null,
            'activated_by' => null,
            'note'         => 'Seeded from the behaviour MEDAXIS already had: lowest priority rank with capacity. '
                . 'Activating any other version is a deliberate change to who receives which case.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_policies');
    }
};
