<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

class NadQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        if (Questionnaire::where('name', 'NAD Questionnaire')->exists()) {
            $this->command->info('NAD Questionnaire already seeded — skipping.');
            return;
        }

        $q = Questionnaire::create([
            'name'        => 'NAD Questionnaire',
            'description' => 'NAD+ therapy safety screen covering cardiac history.',
            'mode'        => 'multi',
            'purpose'     => 'clinical',
            'is_active'   => true,
        ]);

        $q->questions()->create([
            'question'    => 'Do you now, or have you ever had, any heart arrhythmia or irregular heartbeat?',
            'key'         => 'heart_arrhythmia',
            'type'        => 'radio',
            'placeholder' => null,
            'is_required' => true,
            'is_readonly' => false,
            'is_active'   => true,
            'options'     => [
                ['value' => 'Yes', 'is_disqualify' => true],
                ['value' => 'No',  'is_disqualify' => false],
            ],
            'sort_order'  => 0,
            'step_number' => 1,
        ]);

        $this->command->info('NAD Questionnaire seeded.');
    }
}
