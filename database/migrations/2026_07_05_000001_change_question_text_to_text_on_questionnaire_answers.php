<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_answers', function (Blueprint $table) {
            $table->text('question_text')->change();
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_answers', function (Blueprint $table) {
            $table->string('question_text', 500)->change();
        });
    }
};
