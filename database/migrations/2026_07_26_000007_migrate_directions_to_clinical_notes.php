<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// C8: one-time data migration — existing case_prescriptions.directions rows that
// have text become internal ClinicalNotes (is_private = 1, type = 'internal').
// The directions column is kept on case_prescriptions for historical reference
// but will no longer be populated going forward.
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('case_prescriptions')
            ->whereNotNull('directions')
            ->where('directions', '!=', '')
            ->select('id', 'case_id', 'clinician_id', 'directions', 'prescribed_at')
            ->get();

        foreach ($rows as $row) {
            $exists = DB::table('clinical_notes')
                ->where('case_id', $row->case_id)
                ->where('clinician_id', $row->clinician_id)
                ->where('type', 'internal')
                ->where('note', $row->directions)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('clinical_notes')->insert([
                'uuid'         => (string) Str::uuid(),
                'case_id'      => $row->case_id,
                'clinician_id' => $row->clinician_id,
                'type'         => 'internal',
                'note'         => $row->directions,
                'is_private'   => 1,
                'created_at'   => $row->prescribed_at ?? now(),
                'updated_at'   => $row->prescribed_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        // Not reversible — removing migrated notes would require knowing which
        // clinical_notes came from this migration vs. were added manually.
    }
};
