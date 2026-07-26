<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Stamped when a pull request is granted. Null = no deadline set.
            $table->timestamp('completion_deadline_at')->nullable()->after('support_at');
            // True once the 15-minute warning notification has been sent, so the
            // command does not spam the clinician on every run.
            $table->boolean('deadline_warned')->default(false)->after('completion_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['completion_deadline_at', 'deadline_warned']);
        });
    }
};
