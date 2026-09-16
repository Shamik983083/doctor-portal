<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_physicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('npi', 20)->nullable();
            $table->json('licensed_states')->nullable(); // array of state abbreviations e.g. ["AL","FL"]
            $table->timestamps();
        });

        // Create the role if it doesn't already exist.
        $tableNames = config('permission.table_names', ['roles' => 'roles']);
        $rolesTable = $tableNames['roles'] ?? 'roles';

        if (! DB::table($rolesTable)->where('name', 'supervisor_physician')->exists()) {
            DB::table($rolesTable)->insert([
                'name'       => 'supervisor_physician',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_physicians');

        DB::table(config('permission.table_names.roles', 'roles'))
            ->where('name', 'supervisor_physician')
            ->delete();
    }
};
