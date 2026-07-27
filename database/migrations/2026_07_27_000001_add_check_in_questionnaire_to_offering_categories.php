<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offering_categories', function (Blueprint $table) {
            $table->foreignId('check_in_questionnaire_id')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('questionnaires')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offering_categories', function (Blueprint $table) {
            $table->dropForeign(['check_in_questionnaire_id']);
            $table->dropColumn('check_in_questionnaire_id');
        });
    }
};
