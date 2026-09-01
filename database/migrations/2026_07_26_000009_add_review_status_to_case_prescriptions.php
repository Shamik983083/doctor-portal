<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_prescriptions', function (Blueprint $table) {
            // 'draft'     = saved but not yet confirmed; case still in 'assigned'
            // 'confirmed' = provider confirmed on the review page; approve() fired
            $table->string('review_status', 20)->default('confirmed')->after('prescribed_at');
            $table->text('charting_note')->nullable()->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('case_prescriptions', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'charting_note']);
        });
    }
};
