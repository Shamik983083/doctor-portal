<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable(); // male, female, other
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('country', 2)->default('US');
            $table->string('status')->default('active'); // active, inactive, deleted
            $table->string('dosespot_patient_id')->nullable();
            $table->boolean('email_opt_in')->default(true);
            $table->boolean('sms_opt_in')->default(true);
            $table->string('id_verified_status')->nullable(); // pending, verified, failed
            $table->timestamp('id_verified_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['partner_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
