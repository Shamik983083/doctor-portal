<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Cases
            'view cases', 'create cases', 'update cases', 'delete cases',
            'assign cases', 'approve cases', 'cancel cases',
            // Patients
            'view patients', 'create patients', 'update patients', 'delete patients',
            // Offerings
            'view offerings', 'create offerings', 'update offerings', 'delete offerings',
            // Orders
            'view orders', 'update orders',
            // Clinical
            'add clinical notes', 'send messages',
            // Admin
            'manage partners', 'manage clinicians', 'manage system',
            // Webhooks
            'manage webhooks',
            // Super Admin
            'manage admins',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        /*
         * TWO TIERS (Devin msg 2117). Before this, `admin` was granted
         * Permission::all(), which made it identical to `super_admin`: the two
         * roles existed but meant nothing. A Doctor Admin now gets operational
         * permissions over the doctors they are over, and NOT the ones that
         * configure the platform or reach outside it.
         *
         * Withheld from admin, deliberately, and held only by super_admin:
         *   manage partners  - storefronts and their Healthie credentials
         *   manage webhooks  - an outbound integration
         *   manage system    - settings, catalog, triage rules, AI instructions
         *   manage admins    - who is an admin, and which doctors they are over
         *
         * "All API integrations etc should be a super admin function." Anything
         * that reaches outside MEDAXIS is on that list.
         *
         * NOTE FOR WHOEVER RUNS THIS: syncPermissions REPLACES the role's
         * permissions, so re-running this seeder DEMOTES existing admins from
         * full access to scoped access. That is the intended change, but it is a
         * live permission change and should be run knowingly, not incidentally.
         */
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions([
            'view cases', 'create cases', 'update cases', 'delete cases',
            'assign cases', 'approve cases', 'cancel cases',
            'view patients', 'create patients', 'update patients', 'delete patients',
            'view offerings',
            'view orders', 'update orders',
            'add clinical notes', 'send messages',
            'manage clinicians',
        ]);

        $clinicianRole = Role::firstOrCreate(['name' => 'clinician']);
        $clinicianRole->syncPermissions([
            'view cases', 'assign cases', 'approve cases', 'cancel cases',
            'view patients',
            'add clinical notes', 'send messages',
            'view orders',
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdminRole->syncPermissions(Permission::all());

        $partnerRole = Role::firstOrCreate(['name' => 'partner']);
        $partnerRole->syncPermissions([
            'view cases', 'create cases',
            'view patients', 'create patients', 'update patients',
            'view offerings',
            'view orders',
            'manage webhooks',
        ]);
    }
}
