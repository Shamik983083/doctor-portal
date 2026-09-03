<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_sub_storefront', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')
                ->constrained('clinicians')
                ->cascadeOnDelete();
            $table->foreignId('sub_storefront_id')
                ->constrained('sub_storefronts')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['clinician_id', 'sub_storefront_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_sub_storefront');
    }
};
