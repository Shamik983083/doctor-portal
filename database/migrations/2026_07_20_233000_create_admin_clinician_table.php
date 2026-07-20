<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which doctors an admin is over.
 *
 * The two tiers (Devin msg 2117): super_admin is us and sees everything; admin
 * is "over specific doctors" and sees only their work.
 *
 * NOTE A DELIBERATE DIVERGENCE FROM MA-DOCPORTAL. MA scopes an admin to
 * STOREFRONTS ("admins see only storefronts explicitly assigned to them"). Here
 * an admin is scoped to CLINICIANS. Different axis, chosen on purpose: this is a
 * Doctor Admin, someone who runs a group of doctors, not a storefront operator.
 * If storefront scoping is ever wanted too, it is a second pivot alongside this
 * one rather than a change to it.
 *
 * ADDITIVE ONLY. This app runs `artisan migrate --force` on push to main and
 * staging, so it executes on merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_clinician', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // the admin
            $table->foreignId('clinician_id')->constrained()->cascadeOnDelete();  // the doctor they are over
            $table->timestamps();

            // An admin is over a doctor once. Two rows would double every count
            // on every scoped screen, which is the kind of bug that looks like a
            // data problem for a week.
            $table->unique(['user_id', 'clinician_id'], 'admin_clinician_unique');
            $table->index('clinician_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_clinician');
    }
};
