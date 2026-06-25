<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('case_offering_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dosespot_prescription_id')->nullable();
            $table->string('status')->default('pending');
            // pending, sent, approved, denied, cancelled, expired
            $table->string('drug_name')->nullable();
            $table->string('strength')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('days_supply')->nullable();
            $table->integer('refills')->default(0);
            $table->text('directions')->nullable();
            $table->text('pharmacy_notes')->nullable();
            $table->boolean('is_daw')->default(false);
            $table->timestamp('written_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
