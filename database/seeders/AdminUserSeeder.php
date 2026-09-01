<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default', 'status' => 'active']
        );

        $user = User::firstOrCreate(
            ['email' => 'admin@loanbot.com'],
            [
                'tenant_id'      => $tenant->id,
                'name'           => 'Admin',
                'password'       => Hash::make('password'),
                'is_super_admin' => true,
            ]
        );

        if (!$user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        $this->command->info('Admin user created: admin@loanbot.com / password');
    }
}
