<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label');
            $table->string('group')->default('general');
            $table->string('type')->default('text'); // text, number, boolean
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            [
                'key'         => 'sla_pickup_hours',
                'value'       => '4',
                'label'       => 'Queue Pickup Deadline',
                'group'       => 'sla',
                'type'        => 'number',
                'description' => 'Maximum hours for a case to move from waiting to assigned.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'sla_review_hours',
                'value'       => '24',
                'label'       => 'Review & Approval Deadline',
                'group'       => 'sla',
                'type'        => 'number',
                'description' => 'Maximum hours for a clinician to approve or decline after assignment.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'sla_total_hours',
                'value'       => '48',
                'label'       => 'End-to-End Case Deadline',
                'group'       => 'sla',
                'type'        => 'number',
                'description' => 'Maximum hours from case creation to completion.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
