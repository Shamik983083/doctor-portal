<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('sub_storefront_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('sub_storefronts')
                ->nullOnDelete();

            $table->index('sub_storefront_id');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['sub_storefront_id']);
            $table->dropConstrainedForeignId('sub_storefront_id');
        });
    }
};
