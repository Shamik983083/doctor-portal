<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add bundle_group to case_offerings.
 *
 * bundle_group — opaque string set by the partner when multiple offerings
 *   belong to a single atomic bundle (e.g. "semaglutide + NAD combo").
 *   All rows sharing the same bundle_group are treated as one unit on the
 *   clinician prescribe screen: dropdowns are filtered to each component's
 *   drug family, and removing any row removes all rows in the group.
 *   Null means a standalone (non-bundle) offering — existing behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_offerings', function (Blueprint $table) {
            $table->string('bundle_group', 100)->nullable()->after('product_key');
        });
    }

    public function down(): void
    {
        Schema::table('case_offerings', function (Blueprint $table) {
            $table->dropColumn('bundle_group');
        });
    }
};
