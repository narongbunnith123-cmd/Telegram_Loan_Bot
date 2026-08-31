<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage groups',
            'approve groups',
            'create loans',
            'edit loans',
            'cancel loans',
            'mark payments',
            'approve payment proof',
            'configure reminders',
            'export reports',
            'view own loan',
            'upload payment proof',
            'use bot commands',
            'view activity logs',
            'manage blacklist',
            'manage users',
            'register bot token',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $groupAdmin = Role::firstOrCreate(['name' => 'group_admin']);
        $borrower   = Role::firstOrCreate(['name' => 'borrower']);

        $superAdmin->givePermissionTo(Permission::all());

        $groupAdmin->givePermissionTo([
            'manage groups',
            'create loans',
            'edit loans',
            'cancel loans',
            'mark payments',
            'approve payment proof',
            'configure reminders',
            'export reports',
            'view activity logs',
            'manage blacklist',
            'register bot token',
        ]);

        $borrower->givePermissionTo([
            'view own loan',
            'upload payment proof',
            'use bot commands',
        ]);

        $this->command->info('Roles and permissions seeded.');
    }
}
