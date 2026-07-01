<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('depends_on_question_id')->nullable()->after('sort_order');
            $table->string('depends_on_operator', 20)->nullable()->after('depends_on_question_id');
            $table->string('depends_on_value', 500)->nullable()->after('depends_on_operator');

            $table->foreign('depends_on_question_id')
                  ->references('id')
                  ->on('questionnaire_questions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->dropForeign(['depends_on_question_id']);
            $table->dropColumn(['depends_on_question_id', 'depends_on_operator', 'depends_on_value']);
        });
    }
};
