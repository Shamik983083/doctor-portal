<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->unsignedBigInteger('oauth_client_id')->nullable()->after('webhook_secret');
            $table->string('client_id')->nullable()->after('oauth_client_id');
            $table->string('client_secret')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['oauth_client_id', 'client_id', 'client_secret']);
        });
    }
};
