<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->enum('mode', ['single', 'multi'])->default('single')->after('is_active');
        });

        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->unsignedTinyInteger('step_number')->default(1)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropColumn('mode');
        });

        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->dropColumn('step_number');
        });
    }
};
