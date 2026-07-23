<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which product categories each doctor will accept (Devin msg 2313 Q2).
 *
 * "Currently we have GLP, NAD, Anti-Aging, Peptides, ED. We want to be able to
 * add and remove as we go."
 *
 * THE TAXONOMY ALREADY EXISTS AND IS ALREADY EDITABLE. `offering_categories` is a
 * global table (id, name, description, is_active), not a per-partner one, with an
 * admin CRUD screen at /admin/categories that can add, deactivate and delete. So
 * this needs no new taxonomy: it needs the join between a doctor and the
 * categories they take, which is what this table is. A category added next month
 * is immediately tickable with no code change.
 *
 * WHY BACKFILL EVERY DOCTOR WITH EVERY CATEGORY.
 * The gate this feeds is fail-closed: a doctor is blocked from a case whose
 * category they have not ticked. On an empty table that blocks EVERY doctor from
 * EVERY case the moment it deploys, because nobody has ticked anything yet.
 * Backfilling reproduces today's behaviour exactly (every doctor takes
 * everything) and makes "unticked" mean a deliberate admin choice rather than an
 * artefact of the migration. Same reasoning as the clinician_partner backfill on
 * compliance/rxos-laws.
 *
 * Soft-deleted clinicians are skipped: they are not routing candidates, and
 * restoring one is rare enough to be worth an admin re-ticking their categories.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_offering_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained('clinicians')->cascadeOnDelete();
            $table->foreignId('offering_category_id')->constrained('offering_categories')->cascadeOnDelete();
            $table->timestamps();

            // A doctor accepts a category once or not at all. The unique index is
            // the thing that makes a double-submitted form harmless.
            $table->unique(['clinician_id', 'offering_category_id'], 'clinician_category_unique');
        });

        $clinicians = DB::table('clinicians')->whereNull('deleted_at')->pluck('id');
        $categories = DB::table('offering_categories')->where('is_active', true)->pluck('id');

        if ($clinicians->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $now  = now();
        $rows = [];

        foreach ($clinicians as $clinicianId) {
            foreach ($categories as $categoryId) {
                $rows[] = [
                    'clinician_id'         => $clinicianId,
                    'offering_category_id' => $categoryId,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        // Chunked because this is a cross product and the row count is
        // doctors x categories, which is small today and need not stay small.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('clinician_offering_category')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_offering_category');
    }
};
