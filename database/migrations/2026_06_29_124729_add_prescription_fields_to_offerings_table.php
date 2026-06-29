<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->string('internal_name')->nullable()->after('name');
            $table->text('compound_formula')->nullable()->after('internal_name');
            $table->unsignedSmallInteger('refills')->nullable()->after('compound_formula');
            $table->decimal('quantity', 8, 2)->nullable()->after('refills');
            $table->unsignedSmallInteger('days_supply')->nullable()->after('quantity');
            $table->string('dispense_unit')->nullable()->after('days_supply');
            $table->unsignedSmallInteger('days_until_dispense')->nullable()->after('dispense_unit');
            $table->text('directions')->nullable()->after('days_until_dispense');
            $table->string('pharmacy_name')->nullable()->after('directions');
            $table->text('pharmacy_notes')->nullable()->after('pharmacy_name');
        });
    }

    public function down(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->dropColumn([
                'internal_name', 'compound_formula', 'refills', 'quantity',
                'days_supply', 'dispense_unit', 'days_until_dispense',
                'directions', 'pharmacy_name', 'pharmacy_notes',
            ]);
        });
    }
};
