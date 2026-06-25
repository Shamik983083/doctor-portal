<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('global'); // global, partner, case, patient
            $table->string('color')->default('#6c757d');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('case_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'tag_id']);
        });

        Schema::create('patient_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_tags');
        Schema::dropIfExists('case_tags');
        Schema::dropIfExists('tags');
    }
};
