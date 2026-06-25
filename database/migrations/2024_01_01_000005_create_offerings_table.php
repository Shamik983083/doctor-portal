<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('medication'); // medication, compound, supply
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('dosespot_medication_id')->nullable();
            $table->string('boothwyn_compound_id')->nullable();
            $table->string('pharmacy_type')->nullable(); // boothwyn, curexa, custom
            $table->json('available_states')->nullable();
            $table->json('dispense_units')->nullable();
            $table->json('images')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_controlled_substance')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerings');
    }
};
