<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active');
            // active, paused, cancelled, expired, pending
            $table->string('renew_period')->default('monthly'); // weekly, monthly, quarterly, yearly
            $table->string('encounter_period')->nullable();
            $table->decimal('billing_amount', 10, 2)->nullable();
            $table->json('billing_info')->nullable();
            $table->json('products')->nullable();
            $table->timestamp('next_renewal_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_subscriptions');
    }
};
