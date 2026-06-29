<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')
                  ->constrained('questionnaire_responses')
                  ->cascadeOnDelete();
            $table->foreignId('question_id')
                  ->constrained('questionnaire_questions')
                  ->cascadeOnDelete();
            // Frozen at submission time — question label may change later
            $table->string('question_text', 500);
            $table->text('answer')->nullable();
            $table->boolean('is_disqualified')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_answers');
    }
};
