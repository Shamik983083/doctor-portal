<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give `users` the `is_active` column the app already believes it has.
 *
 * AdminUserController::toggleActive() has been writing `is_active` to this
 * table since it was added. The column does not exist and `is_active` was not
 * in User::$fillable either, so Eloquent's mass-assignment guard dropped it
 * before any SQL was built: no error, no exception, and the UI still flashed
 * "Admin account status updated." Deactivating an admin did nothing at all,
 * and said it worked.
 *
 * ADDITIVE, and defaults to TRUE so every existing user stays active on deploy.
 * Nobody is locked out by this migration running.
 *
 * Runs automatically on merge via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
