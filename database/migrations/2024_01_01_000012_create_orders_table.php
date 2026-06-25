<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            // pending, submitted, processing, shipped, delivered, cancelled, returned
            $table->string('tracking_number')->nullable();
            $table->string('tracking_carrier')->nullable();
            $table->string('payment_status')->default('pending');
            // pending, paid, refunded, failed
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('fulfillment_data')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
