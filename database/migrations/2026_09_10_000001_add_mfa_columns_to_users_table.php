<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('force_password_reset')->default(false)->after('is_active');
            $table->string('mfa_code')->nullable()->after('force_password_reset');
            $table->timestamp('mfa_expires_at')->nullable()->after('mfa_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['force_password_reset', 'mfa_code', 'mfa_expires_at']);
        });
    }
};
