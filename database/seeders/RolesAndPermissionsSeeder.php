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
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        $clinicianRole = Role::firstOrCreate(['name' => 'clinician']);
        $clinicianRole->syncPermissions([
            'view cases', 'assign cases', 'approve cases', 'cancel cases',
            'view patients',
            'add clinical notes', 'send messages',
            'view orders',
        ]);

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
