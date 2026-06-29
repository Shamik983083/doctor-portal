<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->string('key')->nullable()->after('question');
            $table->string('placeholder')->nullable()->after('type');
            $table->boolean('is_readonly')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->dropColumn(['key', 'placeholder', 'is_readonly']);
        });
    }
};
