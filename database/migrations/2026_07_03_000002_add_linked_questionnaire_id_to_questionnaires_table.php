<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->foreignId('linked_questionnaire_id')
                  ->nullable()
                  ->after('mode')
                  ->constrained('questionnaires')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropForeign(['linked_questionnaire_id']);
            $table->dropColumn('linked_questionnaire_id');
        });
    }
};
