<?php

use App\User;
use Bican\Roles\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminRole = Role::create([
            'name' => 'Administrators',
            'slug' => 'admin'
        ]);

        $userRole = Role::create([
            'name' => 'Developers',
            'slug' => 'developer'
        ]);

        $admin = User::whereName('Admin')->first();
        $user  = User::whereName('Developer')->first();

        $admin->attachRole($adminRole);
        $user->attachRole($userRole);
    }
}
