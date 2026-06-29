<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->nullable()
                  ->after('partner_id')
                  ->constrained('offering_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\OfferingCategory::class);
            $table->dropColumn('category_id');
        });
    }
};
