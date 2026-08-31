<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_ehr_settings', function (Blueprint $table) {
            $table->string('default_group_id')->nullable()->after('note_form_id');
        });
    }

    public function down(): void
    {
        Schema::table('partner_ehr_settings', function (Blueprint $table) {
            $table->dropColumn('default_group_id');
        });
    }
};
