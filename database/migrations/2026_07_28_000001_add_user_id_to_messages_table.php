<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // DA4: identifies the admin (User) who sent an internal-channel message.
            // Nullable on all existing rows and on patient/clinician-originated messages.
            // nullOnDelete keeps historical records if the admin account is removed.
            $table->foreignId('user_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
