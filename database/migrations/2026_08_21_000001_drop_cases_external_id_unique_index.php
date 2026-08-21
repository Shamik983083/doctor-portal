<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique(['partner_id', 'external_id']);
            $table->index(['partner_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['partner_id', 'external_id']);
            $table->unique(['partner_id', 'external_id']);
        });
    }
};
