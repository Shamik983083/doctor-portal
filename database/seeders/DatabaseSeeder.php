<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            IntakeQuestionnairesSeeder::class,
            OfferIntakeQuestionnairesSeeder::class,
            NadQuestionnaireSeeder::class,
            DemoDataSeeder::class,
            OfferingCategoriesSeeder::class,
            GlpOfferingsSeeder::class,
            NadOfferingsSeeder::class,
            AttachQuestionnairesToOfferingsSeeder::class,
            PartnerProductPlanSeeder::class,
            SettingsSeeder::class,
            AiInstructionSetsSeeder::class,
        ]);
    }
}
