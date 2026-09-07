<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_storefronts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 30)->default('active');

            // Self-contained Healthie EHR credentials — each sub-storefront is its own Healthie sub-org.
            // Mirrors PartnerEhrSetting columns so the adapter can use either interchangeably.
            $table->text('healthie_api_key')->nullable();          // encrypted at rest via model cast
            $table->string('healthie_endpoint')->nullable();
            $table->string('healthie_authorization_shard')->nullable();
            $table->string('healthie_organization_id')->nullable();
            $table->string('healthie_default_provider_id')->nullable();
            $table->string('healthie_note_form_id')->nullable();
            $table->string('healthie_default_group_id')->nullable();
            $table->boolean('healthie_is_enabled')->default(false);
            $table->boolean('healthie_sandbox_validated')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['partner_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_storefronts');
    }
};
