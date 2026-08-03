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
            DemoDataSeeder::class,
            GlpOfferingsSeeder::class,
            AttachQuestionnairesToOfferingsSeeder::class,
            PartnerProductPlanSeeder::class,
        ]);
    }
}
